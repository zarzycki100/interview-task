<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Application;

use Modules\Invoices\Application\Exceptions\InvoiceNotFound;
use Modules\Invoices\Application\UseCases\GetInvoice;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\TestCase;
use Tests\Support\Invoices\InMemoryInvoiceRepository;

final class GetInvoiceTest extends TestCase
{
    public function test_returns_the_invoice_with_its_current_status_and_totals(): void
    {
        $invoices = new InMemoryInvoiceRepository;
        $id = InvoiceId::generate();
        $invoices->add(Invoice::reconstitute(
            $id,
            Customer::reconstitute('Jane Doe', 'jane@example.com'),
            StatusEnum::Sending,
            [ProductLine::reconstitute('Consulting', 2, 15_000), ProductLine::reconstitute('Licence', 1, 500)],
            3,
        ));

        $view = (new GetInvoice($invoices))->handle($id);

        $this->assertSame($id->toString(), $view->id);
        $this->assertSame('sending', $view->status);
        $this->assertSame('Jane Doe', $view->customerName);
        $this->assertSame('jane@example.com', $view->customerEmail);
        $this->assertSame(['Consulting', 'Licence'], array_column($view->productLines, 'name'));
        $this->assertSame(30_500, $view->totalPrice);
    }

    public function test_unknown_invoice_is_not_found(): void
    {
        $this->expectException(InvoiceNotFound::class);

        (new GetInvoice(new InMemoryInvoiceRepository))->handle(InvoiceId::generate());
    }
}
