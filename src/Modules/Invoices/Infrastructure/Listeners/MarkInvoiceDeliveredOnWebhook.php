<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Listeners;

use Modules\Invoices\Application\UseCases\MarkInvoiceAsDelivered;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final readonly class MarkInvoiceDeliveredOnWebhook
{
    public function __construct(
        private MarkInvoiceAsDelivered $markInvoiceAsDelivered,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $this->markInvoiceAsDelivered->handle(InvoiceId::fromUuid($event->resourceId));
    }
}
