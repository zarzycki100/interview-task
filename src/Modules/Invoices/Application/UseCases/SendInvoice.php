<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\UseCases;

use Modules\Invoices\Application\Data\InvoiceView;
use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Application\Exceptions\NotificationHandOffFailed;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\InvoiceRepository;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SendInvoice
{
    private const int COMPENSATION_ATTEMPTS = 2;

    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceNotifier $notifier,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws InvoiceNotFound
     * @throws InvoiceCannotBeSent
     * @throws NotificationHandOffFailed
     */
    public function handle(InvoiceId $id): InvoiceView
    {
        $invoice = $this->invoices->find($id) ?? throw InvoiceNotFound::withId($id);

        $invoice->send();

        try {
            $this->invoices->save($invoice);
        } catch (ConcurrentModification) {
            throw InvoiceCannotBeSent::modifiedConcurrently();
        }

        try {
            $this->notifier->notify($invoice);
        } catch (Throwable $handOffFailure) {
            $this->compensate($id, $handOffFailure);

            throw NotificationHandOffFailed::forInvoice($id, $handOffFailure);
        }

        $current = $this->invoices->find($id) ?? throw InvoiceNotFound::withId($id);

        return InvoiceView::fromInvoice($current);
    }

    private function compensate(InvoiceId $id, Throwable $handOffFailure): void
    {
        $context = ['invoice_id' => $id->toString(), 'hand_off_error' => $handOffFailure::class];

        try {
            for ($attempt = 1; $attempt <= self::COMPENSATION_ATTEMPTS; $attempt++) {
                $invoice = $this->invoices->find($id);

                if ($invoice?->status() !== StatusEnum::Sending) {
                    $this->logger->error('Invoice notification hand-off failed; the invoice is no longer sending and was left unchanged.', $context);

                    return;
                }

                $invoice->revertToDraftAfterFailedHandOff();

                try {
                    $this->invoices->save($invoice);
                } catch (ConcurrentModification) {
                    continue;
                }

                $this->logger->error('Invoice notification hand-off failed; the invoice was reverted to draft.', $context);

                return;
            }

            $this->logger->critical('Invoice notification hand-off failed and the invoice kept changing; it was not reverted.', $context);
        } catch (Throwable $compensationFailure) {
            $this->logger->critical(
                'Invoice notification hand-off failed and the invoice could not be reverted; it is stuck in sending.',
                $context + ['compensation_error' => $compensationFailure::class],
            );
        }
    }
}
