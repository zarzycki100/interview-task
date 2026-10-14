<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Http;

use Illuminate\Support\Str;
use Modules\Notifications\Infrastructure\Drivers\DriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Tests\Support\SpyLogger;
use Tests\TestCase;

final class SendInvoiceTest extends TestCase
{
    public function test_it_sends_a_draft_and_hands_one_e_mail_to_the_provider(): void
    {
        $driver = $this->recordingDriver();
        $id = $this->createInvoice([['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 15_000]]);

        $this->postJson("/api/invoices/{$id}/send")
            ->assertAccepted()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('status', 'sending')
            ->assertJsonPath('total_price', 30_000);

        $this->assertCount(1, $driver->sent);
        $this->assertSame('jane@example.com', $driver->sent[0]['toEmail']);
        $this->assertSame($id, $driver->sent[0]['reference']);
        $this->assertStringContainsString($id, $driver->sent[0]['subject']);
        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'sending');
    }

    public function test_an_invoice_that_is_not_a_draft_cannot_be_sent_again(): void
    {
        $driver = $this->recordingDriver();
        $id = $this->createInvoice([['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]]);
        $this->postJson("/api/invoices/{$id}/send")->assertAccepted();

        $this->postJson("/api/invoices/{$id}/send")
            ->assertConflict()
            ->assertJsonPath('code', 'invoice_not_draft')
            ->assertJsonPath('message', 'Only draft invoices can be sent.');

        $this->assertCount(1, $driver->sent);
    }

    public function test_an_invoice_without_lines_cannot_be_sent(): void
    {
        $driver = $this->recordingDriver();
        $id = $this->createInvoice([]);

        $this->postJson("/api/invoices/{$id}/send")
            ->assertConflict()
            ->assertJsonPath('code', 'invoice_has_no_lines');

        $this->assertSame([], $driver->sent);
        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'draft');
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $driver = $this->recordingDriver();

        $this->postJson('/api/invoices/'.Str::uuid()->toString().'/send')->assertNotFound();

        $this->assertSame([], $driver->sent);
    }

    public function test_a_failed_hand_off_leaves_a_draft_that_can_be_sent_once_the_provider_recovers(): void
    {
        $logger = new SpyLogger;
        $this->app->instance(LoggerInterface::class, $logger);
        $this->app->instance(DriverInterface::class, new class implements DriverInterface
        {
            private bool $available = false;

            public function send(string $toEmail, string $subject, string $message, string $reference): void
            {
                if (! $this->available) {
                    $this->available = true;

                    throw new RuntimeException('provider unavailable');
                }
            }
        });
        $id = $this->createInvoice([['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]]);

        $this->postJson("/api/invoices/{$id}/send")
            ->assertStatus(502)
            ->assertJsonPath('code', 'notification_failed');

        $this->getJson("/api/invoices/{$id}")->assertJsonPath('status', 'draft');
        $errors = $logger->recordsAt(LogLevel::ERROR);
        $this->assertCount(1, $errors);
        $this->assertSame($id, $errors[0]['context']['invoice_id']);

        $this->postJson("/api/invoices/{$id}/send")->assertAccepted()->assertJsonPath('status', 'sending');
    }

    /**
     * @return object{sent: list<array{toEmail: string, subject: string, message: string, reference: string}>}
     */
    private function recordingDriver(): object
    {
        $driver = new class implements DriverInterface
        {
            /** @var list<array{toEmail: string, subject: string, message: string, reference: string}> */
            public array $sent = [];

            public function send(string $toEmail, string $subject, string $message, string $reference): void
            {
                $this->sent[] = compact('toEmail', 'subject', 'message', 'reference');
            }
        };
        $this->app->instance(DriverInterface::class, $driver);

        return $driver;
    }

    /**
     * @param  list<array{name: string, quantity: int, unit_price: int}>  $productLines
     */
    private function createInvoice(array $productLines): string
    {
        $id = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => $productLines,
        ])->assertCreated()->json('id');

        $this->assertIsString($id);

        return $id;
    }
}
