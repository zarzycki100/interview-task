<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\StatusEnum;

final class InvalidStatusTransition extends DomainException
{
    public static function between(StatusEnum $from, StatusEnum $to): self
    {
        return new self("An invoice cannot move from {$from->value} to {$to->value}.");
    }
}
