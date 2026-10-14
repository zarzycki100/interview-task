<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Http;

use Illuminate\Support\Str;
use Tests\TestCase;

final class ShowInvoiceTest extends TestCase
{
    public function test_it_shows_an_invoice_with_its_lines_and_totals(): void
    {
        $id = $this->createInvoice([
            ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 15_000],
            ['name' => 'Licence', 'quantity' => 1, 'unit_price' => 500],
        ]);

        $this->getJson("/api/invoices/{$id}")
            ->assertOk()
            ->assertExactJson([
                'id' => $id,
                'status' => 'draft',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'product_lines' => [
                    ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 15_000, 'total_unit_price' => 30_000],
                    ['name' => 'Licence', 'quantity' => 1, 'unit_price' => 500, 'total_unit_price' => 500],
                ],
                'total_price' => 30_500,
            ]);
    }

    public function test_an_invoice_without_lines_has_an_empty_list_and_zero_total(): void
    {
        $id = $this->createInvoice([]);

        $this->getJson("/api/invoices/{$id}")
            ->assertOk()
            ->assertJsonPath('product_lines', [])
            ->assertJsonPath('total_price', 0);
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $this->getJson('/api/invoices/'.Str::uuid()->toString())
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_a_malformed_id_is_not_found(): void
    {
        $this->get('/api/invoices/not-a-uuid')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    /**
     * @param  list<array{name: string, quantity: int, unit_price: int}>  $productLines
     */
    private function createInvoice(array $productLines): string
    {
        $id = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => $productLines,
        ])->assertCreated()->json('id');

        $this->assertIsString($id);

        return $id;
    }
}
