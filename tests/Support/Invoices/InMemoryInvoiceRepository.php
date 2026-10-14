<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final class InMemoryInvoiceRepository implements InvoiceRepository
{
    /** @var array<string, array{invoice: Invoice, status: StatusEnum, version: int}> */
    private array $rows = [];

    public function add(Invoice $invoice): void
    {
        $this->rows[$invoice->id()->toString()] = [
            'invoice' => $invoice,
            'status' => $invoice->status(),
            'version' => $invoice->version(),
        ];
    }

    public function find(InvoiceId $id): ?Invoice
    {
        $row = $this->rows[$id->toString()] ?? null;

        if ($row === null) {
            return null;
        }

        return Invoice::reconstitute(
            $row['invoice']->id(),
            $row['invoice']->customer(),
            $row['status'],
            $row['invoice']->productLines(),
            $row['version'],
        );
    }

    public function save(Invoice $invoice): void
    {
        $key = $invoice->id()->toString();

        if (($this->rows[$key]['version'] ?? null) !== $invoice->version()) {
            throw ConcurrentModification::of($invoice->id());
        }

        $this->rows[$key]['status'] = $invoice->status();
        $this->rows[$key]['version'] = $invoice->version() + 1;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
