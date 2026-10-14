<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\SendRejectionReason;

final class InvoiceCannotBeSent extends DomainException
{
    private function __construct(
        string $message,
        public readonly SendRejectionReason $reason,
    ) {
        parent::__construct($message);
    }

    public static function notADraft(): self
    {
        return new self('Only draft invoices can be sent.', SendRejectionReason::NotADraft);
    }

    public static function withoutProductLines(): self
    {
        return new self('An invoice without product lines cannot be sent.', SendRejectionReason::NoProductLines);
    }

    public static function withInvalidProductLines(): self
    {
        return new self(
            'An invoice can only be sent when every product line has a positive quantity and unit price.',
            SendRejectionReason::InvalidProductLines,
        );
    }

    public static function modifiedConcurrently(): self
    {
        return new self('Only draft invoices can be sent.', SendRejectionReason::ModifiedConcurrently);
    }
}
