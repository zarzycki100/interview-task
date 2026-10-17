<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Enums;

enum SendRejectionReason
{
    case NotADraft;
    case NoProductLines;
    case InvalidProductLines;
    case ModifiedConcurrently;
}
