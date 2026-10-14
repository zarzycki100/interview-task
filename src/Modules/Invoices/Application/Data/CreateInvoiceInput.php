<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Data;

final readonly class CreateInvoiceInput
{
    /**
     * @param  list<ProductLineInput>  $productLines
     */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $productLines,
    ) {}
}
