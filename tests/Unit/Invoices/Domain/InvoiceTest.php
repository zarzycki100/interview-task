<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function test_draft_without_product_lines_has_zero_total(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), $this->customer(), []);

        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertSame([], $invoice->productLines());
        $this->assertSame(0, $invoice->totalPrice());
        $this->assertSame(0, $invoice->version());
    }

    public function test_total_price_is_the_sum_of_line_totals(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), $this->customer(), [
            ProductLine::create('Consulting', 2, 15_000),
            ProductLine::create('Licence', 3, 999),
        ]);

        $this->assertSame([30_000, 2_997], array_map(
            static fn (ProductLine $line): int => $line->totalUnitPrice(),
            $invoice->productLines(),
        ));
        $this->assertSame(32_997, $invoice->totalPrice());
    }

    public function test_product_lines_keep_their_order(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), $this->customer(), [
            ProductLine::create('first', 1, 1),
            ProductLine::create('second', 1, 1),
        ]);

        $this->assertSame(['first', 'second'], array_map(
            static fn (ProductLine $line): string => $line->name,
            $invoice->productLines(),
        ));
    }

    public function test_draft_is_rejected_when_the_total_price_does_not_fit_an_integer(): void
    {
        $maxLine = ProductLine::create('max', 2_147_483_647, 2_147_483_647);

        $this->expectException(InvalidInvoice::class);

        Invoice::draft(InvoiceId::generate(), $this->customer(), [$maxLine, $maxLine, $maxLine]);
    }

    public function test_reconstitute_keeps_status_and_version(): void
    {
        $id = InvoiceId::generate();

        $invoice = Invoice::reconstitute($id, $this->customer(), StatusEnum::Sending, [], 7);

        $this->assertTrue($invoice->id()->equals($id));
        $this->assertSame(StatusEnum::Sending, $invoice->status());
        $this->assertSame(7, $invoice->version());
    }

    private function customer(): Customer
    {
        return Customer::create('Jane Doe', 'jane@example.com');
    }
}
