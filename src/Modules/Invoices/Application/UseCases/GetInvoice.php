<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\UseCases;

use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;

final readonly class GetInvoice
{
    public function __construct(
        private InvoiceRepository $invoices,
    ) {}

    public function handle(InvoiceId $id): InvoiceView
    {
        $invoice = $this->invoices->find($id) ?? throw InvoiceNotFound::withId($id);

        return InvoiceView::fromInvoice($invoice);
    }
}
