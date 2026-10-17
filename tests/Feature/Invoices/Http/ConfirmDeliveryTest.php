<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Http;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Modules\Notifications\Infrastructure\Drivers\DriverInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ConfirmDeliveryTest extends TestCase
{
    public function test_create_send_confirm_and_view_end_to_end(): void
    {
        $this->app->instance(DriverInterface::class, $this->silentDriver());
        $id = $this->createInvoice();
        $this->postJson("/api/invoices/{$id}/send")->assertAccepted()->assertJsonPath('status', 'sending');

        $this->confirmDelivery($id)->assertNoContent();

        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'sent-to-client');
    }

    public function test_a_confirmation_arriving_during_the_send_is_not_lost(): void
    {
        $events = $this->app->make(Dispatcher::class);
        $this->app->instance(DriverInterface::class, new class($events) implements DriverInterface
        {
            public function __construct(private readonly Dispatcher $events) {}

            public function send(string $toEmail, string $subject, string $message, string $reference): void
            {
                $this->events->dispatch(new WebhookDeliveredEvent(Uuid::fromString($reference)));
            }
        });
        $id = $this->createInvoice();

        $this->postJson("/api/invoices/{$id}/send")
            ->assertAccepted()
            ->assertJsonPath('status', 'sent-to-client');

        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'sent-to-client');
    }

    public function test_a_confirmation_for_a_draft_is_acknowledged_and_changes_nothing(): void
    {
        $id = $this->createInvoice();

        $this->confirmDelivery($id)->assertNoContent();

        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'draft');
    }

    public function test_a_repeated_confirmation_is_harmless(): void
    {
        $this->app->instance(DriverInterface::class, $this->silentDriver());
        $id = $this->createInvoice();
        $this->postJson("/api/invoices/{$id}/send")->assertAccepted();
        $this->confirmDelivery($id)->assertNoContent();
        $after = DB::table('invoices')->where('id', $id)->first();

        $this->confirmDelivery($id)->assertNoContent();

        $this->assertEquals($after, DB::table('invoices')->where('id', $id)->first());
    }

    public function test_an_unknown_reference_is_indistinguishable_from_an_ignored_one(): void
    {
        $existing = $this->confirmDelivery($this->createInvoice());
        $unknown = $this->confirmDelivery(Str::uuid()->toString());

        $unknown->assertNoContent();
        $this->assertSame($existing->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($existing->getContent(), $unknown->getContent());
    }

    public function test_a_technical_failure_is_not_acknowledged(): void
    {
        $this->app->instance(InvoiceRepository::class, new class implements InvoiceRepository
        {
            public function add(Invoice $invoice): void {}

            public function find(InvoiceId $id): ?Invoice
            {
                throw new RuntimeException('database unavailable');
            }

            public function save(Invoice $invoice): void {}
        });

        $this->confirmDelivery(Str::uuid()->toString())->assertServerError();
    }

    /**
     * @return TestResponse<Response>
     */
    private function confirmDelivery(string $reference): TestResponse
    {
        return $this->get(route('notification.hook', ['action' => 'delivered', 'reference' => $reference]));
    }

    private function silentDriver(): DriverInterface
    {
        return new class implements DriverInterface
        {
            public function send(string $toEmail, string $subject, string $message, string $reference): void {}
        };
    }

    private function createInvoice(): string
    {
        $id = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]],
        ])->assertCreated()->json('id');

        $this->assertIsString($id);

        return $id;
    }
}
