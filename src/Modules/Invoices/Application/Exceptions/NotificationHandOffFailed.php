<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use RuntimeException;
use Throwable;

final class NotificationHandOffFailed extends RuntimeException
{
    public static function forInvoice(InvoiceId $id, Throwable $previous): self
    {
        return new self("The notification for invoice {$id->toString()} could not be handed over.", 0, $previous);
    }
}
