<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Infrastructure\Listeners\MarkInvoiceDeliveredOnWebhook;
use Modules\Invoices\Infrastructure\Notifications\NotificationsModuleInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceNotifier::class, NotificationsModuleInvoiceNotifier::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, MarkInvoiceDeliveredOnWebhook::class);
    }
}
