<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Ramsey\Uuid\Uuid;

final readonly class InvoiceMapper
{
    public function toDomain(InvoiceRecord $record): Invoice
    {
        return Invoice::reconstitute(
            InvoiceId::fromString($record->id),
            Customer::reconstitute($record->customer_name, $record->customer_email),
            StatusEnum::from($record->status),
            array_values($record->productLines
                ->map(static fn (ProductLineRecord $line): ProductLine => ProductLine::reconstitute($line->name, $line->quantity, $line->unit_price))
                ->all()),
            $record->version,
        );
    }

    /**
     * @return array{id: string, customer_name: string, customer_email: string, status: string, version: int}
     */
    public function toInvoiceAttributes(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id()->toString(),
            'customer_name' => $invoice->customer()->name,
            'customer_email' => $invoice->customer()->email,
            'status' => $invoice->status()->value,
            'version' => $invoice->version(),
        ];
    }

    /**
     * @return list<array{id: string, invoice_id: string, position: int, name: string, quantity: int, unit_price: int, created_at: string, updated_at: string}>
     */
    public function toProductLineRows(Invoice $invoice, string $timestamp): array
    {
        $rows = [];

        foreach ($invoice->productLines() as $position => $line) {
            $rows[] = [
                'id' => Uuid::uuid4()->toString(),
                'invoice_id' => $invoice->id()->toString(),
                'position' => $position,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $rows;
    }
}
