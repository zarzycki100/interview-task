<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Symfony\Component\HttpFoundation\Response;

final class ExceptionMapping
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport([InvalidInvoice::class, InvoiceNotFound::class]);

        $exceptions->render(static fn (InvalidInvoice $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));

        $exceptions->render(static fn (InvoiceNotFound $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_NOT_FOUND,
        ));
    }
}
