<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvalidInvoice extends DomainException
{
    public static function emptyCustomerName(): self
    {
        return new self('The customer name must not be empty.');
    }

    public static function invalidCustomerEmail(): self
    {
        return new self('The customer email must be a valid email address.');
    }

    public static function emptyProductName(): self
    {
        return new self('The product name must not be empty.');
    }

    public static function nonPositiveQuantity(): self
    {
        return new self('The quantity must be a positive integer.');
    }

    public static function nonPositiveUnitPrice(): self
    {
        return new self('The unit price must be a positive integer.');
    }

    public static function totalPriceTooLarge(): self
    {
        return new self('The total price of the invoice is too large.');
    }
}
