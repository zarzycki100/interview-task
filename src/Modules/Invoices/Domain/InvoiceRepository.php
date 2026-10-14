<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;

interface InvoiceRepository
{
    public function add(Invoice $invoice): void;

    public function find(InvoiceId $id): ?Invoice;
}
