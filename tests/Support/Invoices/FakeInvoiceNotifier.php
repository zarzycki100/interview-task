<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Closure;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\Invoice;

final class FakeInvoiceNotifier implements InvoiceNotifier
{
    /** @var list<Invoice> */
    public array $notified = [];

    /** @param  (Closure(Invoice): void)|null  $duringHandOff */
    public function __construct(
        private readonly ?Closure $duringHandOff = null,
    ) {}

    public function notify(Invoice $invoice): void
    {
        $this->notified[] = $invoice;

        if ($this->duringHandOff !== null) {
            ($this->duringHandOff)($invoice);
        }
    }
}
