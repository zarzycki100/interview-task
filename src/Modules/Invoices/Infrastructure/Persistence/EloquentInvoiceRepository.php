<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final readonly class EloquentInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private ConnectionInterface $connection,
        private InvoiceMapper $mapper,
    ) {}

    public function add(Invoice $invoice): void
    {
        $this->connection->transaction(function () use ($invoice): void {
            $record = InvoiceRecord::query()->create($this->mapper->toInvoiceAttributes($invoice));
            $lines = $this->mapper->toProductLineRows($invoice, $record->freshTimestampString());

            if ($lines !== []) {
                ProductLineRecord::query()->insert($lines);
            }
        });
    }

    public function find(InvoiceId $id): ?Invoice
    {
        $record = InvoiceRecord::query()->with('productLines')->find($id->toString());

        return $record === null ? null : $this->mapper->toDomain($record);
    }
}
