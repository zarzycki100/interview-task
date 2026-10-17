<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Application;

use Modules\Invoices\Application\Data\CreateInvoiceInput;
use Modules\Invoices\Application\Data\ProductLineInput;
use Modules\Invoices\Application\UseCases\CreateInvoice;
use Modules\Invoices\Domain\Exceptions\InvalidInvoice;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use PHPUnit\Framework\TestCase;
use Tests\Support\Invoices\InMemoryInvoiceRepository;

final class CreateInvoiceTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private CreateInvoice $createInvoice;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->createInvoice = new CreateInvoice($this->invoices);
    }

    public function test_creates_and_stores_a_draft_invoice(): void
    {
        $view = $this->createInvoice->handle(new CreateInvoiceInput('Jane Doe', 'jane@example.com', [
            new ProductLineInput('Consulting', 2, 15_000),
        ]));

        $this->assertSame('draft', $view->status);
        $this->assertSame(30_000, $view->totalPrice);
        $this->assertSame(30_000, $view->productLines[0]->totalUnitPrice);
        $this->assertNotNull($this->invoices->find(InvoiceId::fromString($view->id)));
    }

    public function test_creates_an_invoice_without_product_lines(): void
    {
        $view = $this->createInvoice->handle(new CreateInvoiceInput('Jane Doe', 'jane@example.com', []));

        $this->assertSame([], $view->productLines);
        $this->assertSame(0, $view->totalPrice);
    }

    public function test_every_invoice_gets_a_new_id(): void
    {
        $input = new CreateInvoiceInput('Jane Doe', 'jane@example.com', []);

        $this->assertNotSame($this->createInvoice->handle($input)->id, $this->createInvoice->handle($input)->id);
    }

    public function test_invalid_data_stores_nothing(): void
    {
        try {
            $this->createInvoice->handle(new CreateInvoiceInput('Jane Doe', 'jane@example.com', [
                new ProductLineInput('Consulting', 0, 15_000),
            ]));
            $this->fail('Expected the invoice to be rejected.');
        } catch (InvalidInvoice) {
            $this->assertSame(0, $this->invoices->count());
        }
    }
}
