<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Data\ProductLineView;

final class InvoiceResponse
{
    /**
     * @return array{id: string, status: string, customer_name: string, customer_email: string, product_lines: list<array{name: string, quantity: int, unit_price: int, total_unit_price: int}>, total_price: int}
     */
    public static function fromView(InvoiceView $invoice): array
    {
        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'customer_name' => $invoice->customerName,
            'customer_email' => $invoice->customerEmail,
            'product_lines' => array_map(
                static fn (ProductLineView $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice,
                ],
                $invoice->productLines,
            ),
            'total_price' => $invoice->totalPrice,
        ];
    }
}
