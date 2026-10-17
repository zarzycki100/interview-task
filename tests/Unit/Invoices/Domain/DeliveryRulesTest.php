<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryRulesTest extends TestCase
{
    public function test_a_sending_invoice_becomes_sent_to_client(): void
    {
        $invoice = $this->invoice(StatusEnum::Sending);

        $invoice->markAsDelivered();

        $this->assertSame(StatusEnum::SentToClient, $invoice->status());
    }

    #[DataProvider('statusesOtherThanSending')]
    public function test_only_a_sending_invoice_can_be_marked_as_delivered(StatusEnum $status): void
    {
        $invoice = $this->invoice($status);

        try {
            $invoice->markAsDelivered();
            $this->fail('Expected the transition to be refused.');
        } catch (InvalidStatusTransition) {
            $this->assertSame($status, $invoice->status());
        }
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function statusesOtherThanSending(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent-to-client' => [StatusEnum::SentToClient];
    }

    private function invoice(StatusEnum $status): Invoice
    {
        return Invoice::reconstitute(
            InvoiceId::generate(),
            Customer::create('Jane Doe', 'jane@example.com'),
            $status,
            [ProductLine::create('Item', 1, 100)],
            0,
        );
    }
}
