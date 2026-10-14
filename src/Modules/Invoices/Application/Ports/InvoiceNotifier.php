<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Ports;

use Modules\Invoices\Domain\Invoice;

interface InvoiceNotifier
{
    public function notify(Invoice $invoice): void;
}
