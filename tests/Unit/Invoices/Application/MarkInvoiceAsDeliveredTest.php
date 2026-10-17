<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Application;

use Modules\Invoices\Application\UseCases\MarkInvoiceAsDelivered;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Tests\Support\Invoices\InMemoryInvoiceRepository;
use Tests\Support\SpyLogger;

final class MarkInvoiceAsDeliveredTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->logger = new SpyLogger;
    }

    public function test_a_sending_invoice_becomes_sent_to_client(): void
    {
        $id = $this->addInvoice(StatusEnum::Sending);

        $this->useCase()->handle($id);

        $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($id)?->status());
        $this->assertSame([], $this->logger->records);
    }

    #[DataProvider('statusesThatIgnoreConfirmations')]
    public function test_a_confirmation_for_an_invoice_that_is_not_sending_is_ignored_and_logged(StatusEnum $status): void
    {
        $id = $this->addInvoice($status);

        $this->useCase()->handle($id);

        $this->assertSame($status, $this->invoices->find($id)?->status());
        $info = $this->logger->recordsAt(LogLevel::INFO);
        $this->assertCount(1, $info);
        $this->assertSame($id->toString(), $info[0]['context']['invoice_id']);
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function statusesThatIgnoreConfirmations(): iterable
    {
        yield 'draft, no send in progress (AC-4.2)' => [StatusEnum::Draft];
        yield 'already sent-to-client (AC-4.3)' => [StatusEnum::SentToClient];
    }

    public function test_a_repeated_confirmation_changes_nothing(): void
    {
        $id = $this->addInvoice(StatusEnum::Sending);

        $this->useCase()->handle($id);
        $this->useCase()->handle($id);

        $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($id)?->status());
        $this->assertSame(1, $this->invoices->find($id)->version());
    }

    public function test_a_confirmation_for_an_unknown_reference_is_ignored_and_logged(): void
    {
        $reference = InvoiceId::generate();

        $this->useCase()->handle($reference);

        $info = $this->logger->recordsAt(LogLevel::INFO);
        $this->assertCount(1, $info);
        $this->assertSame($reference->toString(), $info[0]['context']['reference']);
    }

    public function test_a_single_conflict_is_retried_on_fresh_state(): void
    {
        $id = $this->addInvoice(StatusEnum::Sending);
        $repository = $this->conflictingRepository(conflicts: 1);

        (new MarkInvoiceAsDelivered($repository, $this->logger))->handle($id);

        $this->assertSame(StatusEnum::SentToClient, $this->invoices->find($id)?->status());
    }

    public function test_repeated_conflicts_are_not_swallowed(): void
    {
        $id = $this->addInvoice(StatusEnum::Sending);

        $this->expectException(ConcurrentModification::class);

        (new MarkInvoiceAsDelivered($this->conflictingRepository(conflicts: 2), $this->logger))->handle($id);
    }

    public function test_a_technical_failure_propagates(): void
    {
        $broken = new class implements InvoiceRepository
        {
            public function add(Invoice $invoice): void {}

            public function find(InvoiceId $id): ?Invoice
            {
                throw new RuntimeException('database unavailable');
            }

            public function save(Invoice $invoice): void {}
        };

        $this->expectException(RuntimeException::class);

        (new MarkInvoiceAsDelivered($broken, $this->logger))->handle(InvoiceId::generate());
    }

    private function useCase(): MarkInvoiceAsDelivered
    {
        return new MarkInvoiceAsDelivered($this->invoices, $this->logger);
    }

    private function addInvoice(StatusEnum $status): InvoiceId
    {
        $id = InvoiceId::generate();
        $this->invoices->add(Invoice::reconstitute(
            $id,
            Customer::create('Jane Doe', 'jane@example.com'),
            $status,
            [ProductLine::create('Item', 1, 100)],
            0,
        ));

        return $id;
    }

    private function conflictingRepository(int $conflicts): InvoiceRepository
    {
        return new class($this->invoices, $conflicts) implements InvoiceRepository
        {
            public function __construct(
                private readonly InMemoryInvoiceRepository $inner,
                private int $conflictsLeft,
            ) {}

            public function add(Invoice $invoice): void {}

            public function find(InvoiceId $id): ?Invoice
            {
                return $this->inner->find($id);
            }

            public function save(Invoice $invoice): void
            {
                if ($this->conflictsLeft-- > 0) {
                    throw ConcurrentModification::of($invoice->id());
                }
                $this->inner->save($invoice);
            }
        };
    }
}
