<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Application;

use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Application\Exceptions\NotificationHandOffFailed;
use Modules\Invoices\Application\UseCases\SendInvoice;
use Modules\Invoices\Domain\Enums\SendRejectionReason;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Tests\Support\Invoices\FakeInvoiceNotifier;
use Tests\Support\Invoices\InMemoryInvoiceRepository;
use Tests\Support\SpyLogger;

final class SendInvoiceTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->logger = new SpyLogger;
    }

    public function test_it_moves_a_valid_draft_to_sending_and_hands_over_exactly_one_notification(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 2, 100)]);
        $notifier = new FakeInvoiceNotifier;

        $view = $this->sendInvoice($notifier)->handle($id);

        $this->assertSame('sending', $view->status);
        $this->assertSame(StatusEnum::Sending, $this->invoices->find($id)?->status());
        $this->assertCount(1, $notifier->notified);
        $this->assertTrue($notifier->notified[0]->id()->equals($id));
    }

    public function test_an_unknown_invoice_is_not_found_and_nothing_is_sent(): void
    {
        $notifier = new FakeInvoiceNotifier;

        $this->expectException(InvoiceNotFound::class);

        try {
            $this->sendInvoice($notifier)->handle(InvoiceId::generate());
        } finally {
            $this->assertSame([], $notifier->notified);
        }
    }

    public function test_rejected_invoices_are_not_sent_and_keep_their_status(): void
    {
        $cases = [
            [StatusEnum::Sending, [ProductLine::create('Item', 1, 100)], SendRejectionReason::NotADraft],
            [StatusEnum::SentToClient, [ProductLine::create('Item', 1, 100)], SendRejectionReason::NotADraft],
            [StatusEnum::Draft, [], SendRejectionReason::NoProductLines],
            [StatusEnum::Draft, [ProductLine::reconstitute('Item', 0, 100)], SendRejectionReason::InvalidProductLines],
        ];

        foreach ($cases as [$status, $lines, $reason]) {
            $id = $this->addInvoice($status, $lines);
            $notifier = new FakeInvoiceNotifier;

            try {
                $this->sendInvoice($notifier)->handle($id);
                $this->fail("Expected {$reason->name} for a {$status->value} invoice.");
            } catch (InvoiceCannotBeSent $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertSame([], $notifier->notified);
                $this->assertSame($status, $this->invoices->find($id)?->status());
            }
        }
    }

    public function test_a_concurrent_change_is_reported_and_nothing_is_sent(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);
        $notifier = new FakeInvoiceNotifier;
        $conflicting = new class($this->invoices) implements InvoiceRepository
        {
            public function __construct(private readonly InMemoryInvoiceRepository $inner) {}

            public function add(Invoice $invoice): void {}

            public function find(InvoiceId $id): ?Invoice
            {
                return $this->inner->find($id);
            }

            public function save(Invoice $invoice): void
            {
                throw ConcurrentModification::of($invoice->id());
            }
        };

        try {
            (new SendInvoice($conflicting, $notifier, $this->logger))->handle($id);
            $this->fail('Expected a concurrency rejection.');
        } catch (InvoiceCannotBeSent $e) {
            $this->assertSame(SendRejectionReason::ModifiedConcurrently, $e->reason);
            $this->assertSame([], $notifier->notified);
        }
    }

    public function test_a_failed_hand_off_reverts_the_invoice_to_a_draft_that_can_be_sent_again(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);
        $failing = new FakeInvoiceNotifier(static fn () => throw new RuntimeException('provider down'));

        try {
            $this->sendInvoice($failing)->handle($id);
            $this->fail('Expected the hand-off failure to be reported.');
        } catch (NotificationHandOffFailed) {
            $this->assertSame(StatusEnum::Draft, $this->invoices->find($id)?->status());
        }

        $errors = $this->logger->recordsAt(LogLevel::ERROR);
        $this->assertCount(1, $errors);
        $this->assertSame($id->toString(), $errors[0]['context']['invoice_id']);
        $this->assertSame(RuntimeException::class, $errors[0]['context']['hand_off_error']);

        $this->assertSame('sending', $this->sendInvoice(new FakeInvoiceNotifier)->handle($id)->status);
    }

    public function test_a_failed_hand_off_never_overwrites_a_newer_status(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);
        $deliveredDespiteError = new FakeInvoiceNotifier(function (Invoice $invoice): void {
            $this->markAsSentToClient($invoice->id());

            throw new RuntimeException('timeout after the provider accepted the e-mail');
        });

        try {
            $this->sendInvoice($deliveredDespiteError)->handle($id);
            $this->fail('Expected the hand-off failure to be reported.');
        } catch (NotificationHandOffFailed) {
            $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($id)?->status());
        }
    }

    public function test_a_failed_compensation_is_logged_as_critical(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);
        $brokenStorage = new class($this->invoices) implements InvoiceRepository
        {
            private int $saves = 0;

            public function __construct(private readonly InMemoryInvoiceRepository $inner) {}

            public function add(Invoice $invoice): void {}

            public function find(InvoiceId $id): ?Invoice
            {
                return $this->inner->find($id);
            }

            public function save(Invoice $invoice): void
            {
                if (++$this->saves > 1) {
                    throw new RuntimeException('database unavailable');
                }
                $this->inner->save($invoice);
            }
        };
        $failing = new FakeInvoiceNotifier(static fn () => throw new RuntimeException('provider down'));

        try {
            (new SendInvoice($brokenStorage, $failing, $this->logger))->handle($id);
            $this->fail('Expected the hand-off failure to be reported.');
        } catch (NotificationHandOffFailed) {
            $this->assertSame(StatusEnum::Sending, $this->invoices->find($id)?->status());
            $this->assertCount(1, $this->logger->recordsAt(LogLevel::CRITICAL));
        }
    }

    public function test_the_response_reflects_a_delivery_confirmed_during_the_hand_off(): void
    {
        $id = $this->addInvoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);
        $confirmingImmediately = new FakeInvoiceNotifier(fn (Invoice $invoice) => $this->markAsSentToClient($invoice->id()));

        $view = $this->sendInvoice($confirmingImmediately)->handle($id);

        $this->assertSame('sent-to-client', $view->status);
    }

    private function sendInvoice(FakeInvoiceNotifier $notifier): SendInvoice
    {
        return new SendInvoice($this->invoices, $notifier, $this->logger);
    }

    /**
     * @param  list<ProductLine>  $lines
     */
    private function addInvoice(StatusEnum $status, array $lines): InvoiceId
    {
        $id = InvoiceId::generate();
        $this->invoices->add(Invoice::reconstitute($id, Customer::create('Jane Doe', 'jane@example.com'), $status, $lines, 0));

        return $id;
    }

    private function markAsSentToClient(InvoiceId $id): void
    {
        $current = $this->invoices->find($id);
        $this->assertNotNull($current);
        $this->invoices->save(Invoice::reconstitute($id, $current->customer(), StatusEnum::SentToClient, $current->productLines(), $current->version()));
    }
}
