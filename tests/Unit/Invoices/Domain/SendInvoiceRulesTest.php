<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Enums\SendRejectionReason;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SendInvoiceRulesTest extends TestCase
{
    public function test_a_draft_with_valid_lines_moves_to_sending(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft, [ProductLine::create('Item', 1, 100)]);

        $invoice->send();

        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    #[DataProvider('statusesOtherThanDraft')]
    public function test_only_a_draft_can_be_sent(StatusEnum $status): void
    {
        $this->assertSendRejected(
            $this->invoice($status, [ProductLine::create('Item', 1, 100)]),
            SendRejectionReason::NotADraft,
        );
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function statusesOtherThanDraft(): iterable
    {
        yield 'sending' => [StatusEnum::Sending];
        yield 'sent-to-client' => [StatusEnum::SentToClient];
    }

    public function test_an_invoice_without_lines_cannot_be_sent(): void
    {
        $this->assertSendRejected($this->invoice(StatusEnum::Draft, []), SendRejectionReason::NoProductLines);
    }

    #[DataProvider('invalidStoredLines')]
    public function test_an_invoice_with_invalid_stored_lines_cannot_be_sent(ProductLine $invalidLine): void
    {
        $this->assertSendRejected(
            $this->invoice(StatusEnum::Draft, [ProductLine::create('Valid', 1, 100), $invalidLine]),
            SendRejectionReason::InvalidProductLines,
        );
    }

    /** @return iterable<string, array{ProductLine}> */
    public static function invalidStoredLines(): iterable
    {
        yield 'zero quantity' => [ProductLine::reconstitute('Item', 0, 100)];
        yield 'negative unit price' => [ProductLine::reconstitute('Item', 1, -5)];
    }

    public function test_a_sending_invoice_can_be_reverted_to_draft_and_sent_again(): void
    {
        $invoice = $this->invoice(StatusEnum::Sending, [ProductLine::create('Item', 1, 100)]);

        $invoice->revertToDraftAfterFailedHandOff();
        $this->assertSame(StatusEnum::Draft, $invoice->status());

        $invoice->send();
        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    #[DataProvider('statusesOtherThanSending')]
    public function test_only_a_sending_invoice_can_be_reverted(StatusEnum $status): void
    {
        $invoice = $this->invoice($status, [ProductLine::create('Item', 1, 100)]);

        $this->expectException(InvalidStatusTransition::class);

        $invoice->revertToDraftAfterFailedHandOff();
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function statusesOtherThanSending(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent-to-client' => [StatusEnum::SentToClient];
    }

    private function assertSendRejected(Invoice $invoice, SendRejectionReason $reason): void
    {
        $statusBefore = $invoice->status();

        try {
            $invoice->send();
            $this->fail('Expected the invoice to be rejected.');
        } catch (InvoiceCannotBeSent $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame($statusBefore, $invoice->status());
        }
    }

    /**
     * @param  list<ProductLine>  $lines
     */
    private function invoice(StatusEnum $status, array $lines): Invoice
    {
        return Invoice::reconstitute(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), $status, $lines, 0);
    }
}
