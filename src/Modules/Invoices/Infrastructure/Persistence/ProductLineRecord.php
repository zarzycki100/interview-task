<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $invoice_id
 * @property int $position
 * @property string $name
 * @property int $quantity
 * @property int $unit_price
 */
final class ProductLineRecord extends Model
{
    public $incrementing = false;

    protected $table = 'invoice_product_lines';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }
}
