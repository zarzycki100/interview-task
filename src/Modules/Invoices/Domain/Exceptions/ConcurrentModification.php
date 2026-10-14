<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use RuntimeException;

final class ConcurrentModification extends RuntimeException
{
    public static function of(InvoiceId $id): self
    {
        return new self("Invoice {$id->toString()} was modified by another request.");
    }
}
