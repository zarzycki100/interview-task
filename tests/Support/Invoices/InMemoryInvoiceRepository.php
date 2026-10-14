<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final class InMemoryInvoiceRepository implements InvoiceRepository
{
    /** @var array<string, Invoice> */
    private array $invoices = [];

    public function add(Invoice $invoice): void
    {
        $this->invoices[$invoice->id()->toString()] = $invoice;
    }

    public function find(InvoiceId $id): ?Invoice
    {
        return $this->invoices[$id->toString()] ?? null;
    }

    public function count(): int
    {
        return count($this->invoices);
    }
}
