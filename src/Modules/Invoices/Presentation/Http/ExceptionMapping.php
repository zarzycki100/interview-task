<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Application\Exceptions\NotificationHandOffFailed;
use Modules\Invoices\Domain\Enums\SendRejectionReason;
use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Symfony\Component\HttpFoundation\Response;

final class ExceptionMapping
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport([
            InvalidInvoice::class,
            InvoiceNotFound::class,
            InvoiceCannotBeSent::class,
            NotificationHandOffFailed::class,
        ]);

        $exceptions->render(static fn (InvalidInvoice $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage(), 'errors' => ['invoice' => [$e->getMessage()]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));

        $exceptions->render(static fn (InvoiceNotFound $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_NOT_FOUND,
        ));

        $exceptions->render(static fn (InvoiceCannotBeSent $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage(), 'code' => self::sendRejectionCode($e->reason)],
            Response::HTTP_CONFLICT,
        ));

        $exceptions->render(static fn (NotificationHandOffFailed $e): JsonResponse => new JsonResponse(
            ['message' => 'The invoice could not be handed over to the notification provider. Check its status before retrying.', 'code' => 'notification_failed'],
            Response::HTTP_BAD_GATEWAY,
        ));
    }

    private static function sendRejectionCode(SendRejectionReason $reason): string
    {
        return match ($reason) {
            SendRejectionReason::NotADraft => 'invoice_not_draft',
            SendRejectionReason::NoProductLines => 'invoice_has_no_lines',
            SendRejectionReason::InvalidProductLines => 'invoice_has_invalid_lines',
            SendRejectionReason::ModifiedConcurrently => 'invoice_modified_concurrently',
        };
    }
}
