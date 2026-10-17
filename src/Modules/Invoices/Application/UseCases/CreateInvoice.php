<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\UseCases;

use Modules\Invoices\Application\Data\CreateInvoiceInput;
use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Data\ProductLineInput;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;

final readonly class CreateInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
    ) {}

    public function handle(CreateInvoiceInput $input): InvoiceView
    {
        $invoice = Invoice::draft(
            InvoiceId::generate(),
            Customer::create($input->customerName, $input->customerEmail),
            array_map(
                static fn (ProductLineInput $line): ProductLine => ProductLine::create($line->name, $line->quantity, $line->unitPrice),
                $input->productLines,
            ),
        );

        $this->invoices->add($invoice);

        return InvoiceView::fromInvoice($invoice);
    }
}
