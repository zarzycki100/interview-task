<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\UseCases;

use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Psr\Log\LoggerInterface;

final readonly class MarkInvoiceAsDelivered
{
    public function __construct(
        private InvoiceRepository $invoices,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws ConcurrentModification
     */
    public function handle(InvoiceId $id): void
    {
        try {
            $this->apply($id);
        } catch (ConcurrentModification) {
            $this->apply($id);
        }
    }

    private function apply(InvoiceId $id): void
    {
        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            $this->logger->info('Delivery confirmation ignored: no invoice with this reference.', ['reference' => $id->toString()]);

            return;
        }

        try {
            $invoice->markAsDelivered();
        } catch (InvalidStatusTransition) {
            $this->logger->info('Delivery confirmation ignored: the invoice is not being sent.', [
                'invoice_id' => $id->toString(),
                'status' => $invoice->status()->value,
            ]);

            return;
        }

        $this->invoices->save($invoice);
    }
}
