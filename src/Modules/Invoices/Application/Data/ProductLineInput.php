<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Data;

final readonly class ProductLineInput
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {}
}
