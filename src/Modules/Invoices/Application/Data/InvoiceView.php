<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Data;

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\ProductLine;

final readonly class InvoiceView
{
    /**
     * @param  list<ProductLineView>  $productLines
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $customerName,
        public string $customerEmail,
        public array $productLines,
        public int $totalPrice,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id()->toString(),
            status: $invoice->status()->value,
            customerName: $invoice->customer()->name,
            customerEmail: $invoice->customer()->email,
            productLines: array_map(
                static fn (ProductLine $line): ProductLineView => new ProductLineView(
                    name: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                    totalUnitPrice: $line->totalUnitPrice(),
                ),
                $invoice->productLines(),
            ),
            totalPrice: $invoice->totalPrice(),
        );
    }
}
