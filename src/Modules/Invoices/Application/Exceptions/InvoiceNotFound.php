<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use RuntimeException;

final class InvoiceNotFound extends RuntimeException
{
    public static function withId(InvoiceId $id): self
    {
        return new self("Invoice {$id->toString()} was not found.");
    }
}
