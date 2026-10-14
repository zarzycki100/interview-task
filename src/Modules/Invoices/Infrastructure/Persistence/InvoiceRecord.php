<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $status
 * @property int $version
 * @property-read Collection<int, ProductLineRecord> $productLines
 */
final class InvoiceRecord extends Model
{
    public $incrementing = false;

    protected $table = 'invoices';

    protected $keyType = 'string';

    protected $fillable = ['id', 'customer_name', 'customer_email', 'status', 'version'];

    /**
     * @return HasMany<ProductLineRecord, $this>
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductLineRecord::class, 'invoice_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
