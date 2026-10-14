<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Http;

use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateInvoiceTest extends TestCase
{
    public function test_it_creates_a_draft_invoice_with_computed_totals(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [
                ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 15_000],
                ['name' => 'Licence', 'quantity' => 3, 'unit_price' => 999],
            ],
        ]);

        $response->assertCreated()
            ->assertExactJson([
                'id' => $response->json('id'),
                'status' => 'draft',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'product_lines' => [
                    ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 15_000, 'total_unit_price' => 30_000],
                    ['name' => 'Licence', 'quantity' => 3, 'unit_price' => 999, 'total_unit_price' => 2_997],
                ],
                'total_price' => 32_997,
            ])
            ->assertHeader('Location', route('invoices.show', ['invoice' => $response->json('id')]));
        $this->assertDatabaseHas('invoices', ['id' => $response->json('id'), 'status' => 'draft']);
        $this->assertDatabaseCount('invoice_product_lines', 2);
    }

    public function test_it_creates_an_invoice_without_product_lines(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('product_lines', [])
            ->assertJsonPath('total_price', 0);
    }

    public function test_it_ignores_id_status_and_totals_sent_by_the_client(): void
    {
        $response = $this->postJson('/api/invoices', [
            'id' => '00000000-0000-4000-8000-000000000000',
            'status' => 'sent-to-client',
            'total_price' => 1,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [
                ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 100, 'total_unit_price' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('product_lines.0.total_unit_price', 200)
            ->assertJsonPath('total_price', 200);
        $this->assertNotSame('00000000-0000-4000-8000-000000000000', $response->json('id'));
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_rejects_invalid_input_and_stores_nothing(array $override, string $invalidField): void
    {
        $payload = array_replace([
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100]],
        ], $override);

        $this->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$invalidField]);
        $this->assertDatabaseCount('invoices', 0);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        $line = static fn (array $override): array => ['product_lines' => [array_replace(['name' => 'Item', 'quantity' => 1, 'unit_price' => 100], $override)]];

        yield 'missing customer name' => [['customer_name' => null], 'customer_name'];
        yield 'blank customer name' => [['customer_name' => '   '], 'customer_name'];
        yield 'invalid customer email' => [['customer_email' => 'not-an-email'], 'customer_email'];
        yield 'missing product lines' => [['product_lines' => null], 'product_lines'];
        yield 'too many product lines' => [['product_lines' => array_fill(0, 101, ['name' => 'Item', 'quantity' => 1, 'unit_price' => 1])], 'product_lines'];
        yield 'line without a name' => [$line(['name' => '']), 'product_lines.0.name'];
        yield 'zero quantity' => [$line(['quantity' => 0]), 'product_lines.0.quantity'];
        yield 'negative unit price' => [$line(['unit_price' => -1]), 'product_lines.0.unit_price'];
        yield 'fractional quantity' => [$line(['quantity' => 1.5]), 'product_lines.0.quantity'];
        yield 'quantity as a string' => [$line(['quantity' => '2']), 'product_lines.0.quantity'];
        yield 'unit price above 2^31-1' => [$line(['unit_price' => 2_147_483_648]), 'product_lines.0.unit_price'];
    }

    public function test_creating_an_invoice_sends_no_notification(): void
    {
        $this->mock(NotificationFacadeInterface::class)->shouldNotReceive('notify');

        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [],
        ])->assertCreated();
    }

    public function test_validation_errors_are_json_even_without_an_accept_header(): void
    {
        $this->post('/api/invoices', ['customer_name' => 'Jane Doe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_email', 'product_lines']);
    }
}
