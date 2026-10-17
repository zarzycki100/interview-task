<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_customer_name_is_trimmed(): void
    {
        $customer = Customer::create('  Jane Doe ', 'jane@example.com');

        $this->assertSame('Jane Doe', $customer->name);
        $this->assertSame('jane@example.com', $customer->email);
    }

    #[DataProvider('invalidCustomers')]
    public function test_invalid_customer_is_rejected(string $name, string $email): void
    {
        $this->expectException(InvalidInvoice::class);

        Customer::create($name, $email);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidCustomers(): iterable
    {
        yield 'empty name' => ['', 'jane@example.com'];
        yield 'blank name' => ['   ', 'jane@example.com'];
        yield 'invalid email' => ['Jane', 'not-an-email'];
        yield 'empty email' => ['Jane', ''];
    }

    public function test_product_line_total_is_quantity_times_unit_price(): void
    {
        $this->assertSame(4_500, ProductLine::create('Item', 3, 1_500)->totalUnitPrice());
    }

    #[DataProvider('invalidProductLines')]
    public function test_invalid_product_line_is_rejected(string $name, int $quantity, int $unitPrice): void
    {
        $this->expectException(InvalidInvoice::class);

        ProductLine::create($name, $quantity, $unitPrice);
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function invalidProductLines(): iterable
    {
        yield 'empty name' => [' ', 1, 1];
        yield 'zero quantity' => ['Item', 0, 1];
        yield 'negative quantity' => ['Item', -1, 1];
        yield 'zero unit price' => ['Item', 1, 0];
        yield 'negative unit price' => ['Item', 1, -100];
    }

    public function test_reconstituted_product_line_is_not_validated(): void
    {
        $line = ProductLine::reconstitute('Item', 0, 100);

        $this->assertSame(0, $line->quantity);
    }

    public function test_invoice_id_round_trips_through_string(): void
    {
        $id = InvoiceId::generate();

        $this->assertTrue(InvoiceId::fromString($id->toString())->equals($id));
    }
}
