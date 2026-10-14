<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Application\Data\CreateInvoiceInput;
use Modules\Invoices\Application\Data\ProductLineInput;

final class CreateInvoiceRequest extends FormRequest
{
    private const int MAX_PRODUCT_LINES = 100;

    private const int MAX_INTEGER = 2_147_483_647;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email:filter', 'max:255'],
            'product_lines' => ['present', 'list', 'max:'.self::MAX_PRODUCT_LINES],
            'product_lines.*' => ['array'],
            'product_lines.*.name' => ['required', 'string', 'max:255'],
            'product_lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_INTEGER],
            'product_lines.*.unit_price' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_INTEGER],
        ];
    }

    public function toInput(): CreateInvoiceInput
    {
        /** @var array{customer_name: string, customer_email: string, product_lines: list<array{name: string, quantity: int, unit_price: int}>} $data */
        $data = $this->validated();

        return new CreateInvoiceInput(
            customerName: $data['customer_name'],
            customerEmail: $data['customer_email'],
            productLines: array_map(
                static fn (array $line): ProductLineInput => new ProductLineInput($line['name'], $line['quantity'], $line['unit_price']),
                $data['product_lines'],
            ),
        );
    }
}
