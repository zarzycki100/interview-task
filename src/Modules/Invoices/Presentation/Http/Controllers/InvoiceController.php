<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\UseCases\CreateInvoice;
use Modules\Invoices\Application\UseCases\GetInvoice;
use Modules\Invoices\Application\UseCases\SendInvoice;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Presentation\Http\InvoiceResponse;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceRequest;
use Symfony\Component\HttpFoundation\Response;

final readonly class InvoiceController
{
    public function __construct(
        private CreateInvoice $createInvoice,
        private GetInvoice $getInvoice,
        private SendInvoice $sendInvoice,
    ) {}

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->createInvoice->handle($request->toInput());

        return new JsonResponse(
            data: InvoiceResponse::fromView($invoice),
            status: Response::HTTP_CREATED,
            headers: ['Location' => route('invoices.show', ['invoice' => $invoice->id], absolute: false)],
        );
    }

    public function show(string $invoice): JsonResponse
    {
        return new JsonResponse(InvoiceResponse::fromView(
            $this->getInvoice->handle(InvoiceId::fromString($invoice)),
        ));
    }

    public function send(string $invoice): JsonResponse
    {
        return new JsonResponse(
            data: InvoiceResponse::fromView($this->sendInvoice->handle(InvoiceId::fromString($invoice))),
            status: Response::HTTP_ACCEPTED,
        );
    }
}
