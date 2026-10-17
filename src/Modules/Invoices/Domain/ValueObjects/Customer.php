<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoice;

final readonly class Customer
{
    private function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function create(string $name, string $email): self
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidInvoice::emptyCustomerName();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidInvoice::invalidCustomerEmail();
        }

        return new self($name, $email);
    }

    public static function reconstitute(string $name, string $email): self
    {
        return new self($name, $email);
    }
}
