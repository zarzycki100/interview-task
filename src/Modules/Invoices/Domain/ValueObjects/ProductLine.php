<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoice;

final readonly class ProductLine
{
    private function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {}

    public static function create(string $name, int $quantity, int $unitPrice): self
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidInvoice::emptyProductName();
        }

        if ($quantity < 1) {
            throw InvalidInvoice::nonPositiveQuantity();
        }

        if ($unitPrice < 1) {
            throw InvalidInvoice::nonPositiveUnitPrice();
        }

        return new self($name, $quantity, $unitPrice);
    }

    public static function reconstitute(string $name, int $quantity, int $unitPrice): self
    {
        return new self($name, $quantity, $unitPrice);
    }

    public function hasPositiveAmounts(): bool
    {
        return $this->quantity > 0 && $this->unitPrice > 0;
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
