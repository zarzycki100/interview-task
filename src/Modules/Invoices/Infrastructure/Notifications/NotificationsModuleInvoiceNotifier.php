<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\Invoice;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final readonly class NotificationsModuleInvoiceNotifier implements InvoiceNotifier
{
    public function __construct(
        private NotificationFacadeInterface $notifications,
    ) {}

    public function notify(Invoice $invoice): void
    {
        $id = $invoice->id()->toString();

        $this->notifications->notify(new NotifyData(
            resourceId: $invoice->id()->toUuid(),
            toEmail: $invoice->customer()->email,
            subject: "Invoice {$id}",
            message: sprintf(
                "Dear %s,\n\nyour invoice %s with a total of %s has been issued.\n",
                $invoice->customer()->name,
                $id,
                self::formatMinorUnits($invoice->totalPrice()),
            ),
        ));
    }

    private static function formatMinorUnits(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
