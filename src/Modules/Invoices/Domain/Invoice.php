<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;

final class Invoice
{
    /**
     * @param  list<ProductLine>  $productLines
     */
    private function __construct(
        private readonly InvoiceId $id,
        private readonly Customer $customer,
        private readonly StatusEnum $status,
        private readonly array $productLines,
        private readonly int $version,
    ) {}

    /**
     * @param  list<ProductLine>  $productLines
     */
    public static function draft(InvoiceId $id, Customer $customer, array $productLines): self
    {
        self::assertTotalPriceFitsAnInteger($productLines);

        return new self($id, $customer, StatusEnum::Draft, $productLines, 0);
    }

    /**
     * @param  list<ProductLine>  $productLines
     */
    public static function reconstitute(
        InvoiceId $id,
        Customer $customer,
        StatusEnum $status,
        array $productLines,
        int $version,
    ): self {
        return new self($id, $customer, $status, $productLines, $version);
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }

    /**
     * @return list<ProductLine>
     */
    public function productLines(): array
    {
        return $this->productLines;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function totalPrice(): int
    {
        return self::sumOfLineTotals($this->productLines);
    }

    /**
     * @param  list<ProductLine>  $productLines
     */
    private static function assertTotalPriceFitsAnInteger(array $productLines): void
    {
        self::sumOfLineTotals($productLines);
    }

    /**
     * @param  list<ProductLine>  $productLines
     */
    private static function sumOfLineTotals(array $productLines): int
    {
        $total = 0;

        foreach ($productLines as $line) {
            $lineTotal = $line->totalUnitPrice();

            if ($lineTotal > PHP_INT_MAX - $total) {
                throw InvalidInvoice::totalPriceTooLarge();
            }

            $total += $lineTotal;
        }

        return $total;
    }
}
