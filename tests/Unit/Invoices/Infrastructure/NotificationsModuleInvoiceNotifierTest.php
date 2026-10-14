<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Infrastructure;

use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Notifications\NotificationsModuleInvoiceNotifier;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\TestCase;

final class NotificationsModuleInvoiceNotifierTest extends TestCase
{
    public function test_it_notifies_the_customer_with_the_invoice_id_as_reference(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), [
            ProductLine::create('Consulting', 3, 10_005),
        ]);
        $facade = new class implements NotificationFacadeInterface
        {
            /** @var list<NotifyData> */
            public array $sent = [];

            public function notify(NotifyData $data): void
            {
                $this->sent[] = $data;
            }
        };

        (new NotificationsModuleInvoiceNotifier($facade))->notify($invoice);

        $this->assertCount(1, $facade->sent);
        $data = $facade->sent[0];
        $this->assertTrue($data->resourceId->equals($invoice->id()->toUuid()));
        $this->assertSame('jane@example.com', $data->toEmail);
        $this->assertSame('Invoice '.$invoice->id()->toString(), $data->subject);
        $this->assertStringContainsString('Jane Doe', $data->message);
        $this->assertStringContainsString('300.15', $data->message);
    }
}
