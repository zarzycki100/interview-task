<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModification;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\Customer;
use Modules\Invoices\Domain\ValueObjects\InvoiceId;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Tests\TestCase;

final class EloquentInvoiceRepositoryTest extends TestCase
{
    private EloquentInvoiceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(EloquentInvoiceRepository::class);
    }

    public function test_an_invoice_round_trips_with_its_lines_in_order(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), [
            ProductLine::create('first', 2, 1_500),
            ProductLine::create('second', 1, 700),
            ProductLine::create('third', 3, 100),
        ]);

        $this->repository->add($invoice);
        $loaded = $this->repository->find($invoice->id());

        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->id()->equals($invoice->id()));
        $this->assertSame(StatusEnum::Draft, $loaded->status());
        $this->assertSame(0, $loaded->version());
        $this->assertEquals($invoice->customer(), $loaded->customer());
        $this->assertEquals($invoice->productLines(), $loaded->productLines());
        $this->assertSame(4_000, $loaded->totalPrice());
    }

    public function test_an_invoice_without_lines_round_trips(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), []);

        $this->repository->add($invoice);

        $this->assertSame([], $this->repository->find($invoice->id())?->productLines());
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $this->assertNull($this->repository->find(InvoiceId::generate()));
    }

    public function test_loading_an_invoice_takes_two_queries(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), [
            ProductLine::create('first', 1, 1),
            ProductLine::create('second', 1, 1),
        ]);
        $this->repository->add($invoice);

        DB::enableQueryLog();
        $this->repository->find($invoice->id());

        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_a_rejected_line_rolls_back_the_whole_invoice(): void
    {
        $invoice = Invoice::reconstitute(
            InvoiceId::generate(),
            Customer::reconstitute('Jane Doe', 'jane@example.com'),
            StatusEnum::Draft,
            [ProductLine::reconstitute('valid', 1, 100), ProductLine::reconstitute('zero quantity', 0, 100)],
            0,
        );

        try {
            $this->repository->add($invoice);
            $this->fail('Expected the database to reject a line with quantity 0.');
        } catch (QueryException) {
            $this->assertDatabaseCount('invoices', 0);
            $this->assertDatabaseCount('invoice_product_lines', 0);
        }
    }

    public function test_saving_a_status_change_bumps_the_version(): void
    {
        $id = $this->addDraftWithOneLine();
        $invoice = $this->repository->find($id);
        $this->assertNotNull($invoice);

        $invoice->send();
        $this->repository->save($invoice);

        $reloaded = $this->repository->find($id);
        $this->assertSame(StatusEnum::Sending, $reloaded?->status());
        $this->assertSame(1, $reloaded->version());
    }

    public function test_a_stale_copy_cannot_be_saved(): void
    {
        $id = $this->addDraftWithOneLine();
        $first = $this->repository->find($id);
        $second = $this->repository->find($id);
        $this->assertNotNull($first);
        $this->assertNotNull($second);

        $first->send();
        $this->repository->save($first);
        $second->send();

        try {
            $this->repository->save($second);
            $this->fail('Expected the stale copy to be rejected.');
        } catch (ConcurrentModification) {
            $this->assertDatabaseHas('invoices', ['id' => $id->toString(), 'status' => 'sending', 'version' => 1]);
        }
    }

    public function test_the_database_rejects_an_unknown_status(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), []);
        $this->repository->add($invoice);

        $this->expectException(QueryException::class);

        DB::table('invoices')->where('id', $invoice->id()->toString())->update(['status' => 'paid']);
    }

    public function test_deleting_an_invoice_deletes_its_lines(): void
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), [
            ProductLine::create('first', 1, 1),
        ]);
        $this->repository->add($invoice);

        DB::table('invoices')->where('id', $invoice->id()->toString())->delete();

        $this->assertDatabaseCount('invoice_product_lines', 0);
    }

    private function addDraftWithOneLine(): InvoiceId
    {
        $invoice = Invoice::draft(InvoiceId::generate(), Customer::create('Jane Doe', 'jane@example.com'), [
            ProductLine::create('Item', 1, 100),
        ]);
        $this->repository->add($invoice);

        return $invoice->id();
    }
}
