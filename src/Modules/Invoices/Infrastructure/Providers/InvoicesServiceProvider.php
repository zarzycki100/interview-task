<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Infrastructure\Notifications\NotificationsModuleInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;

final class InvoicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceNotifier::class, NotificationsModuleInvoiceNotifier::class);
    }
}
