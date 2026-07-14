<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\ValueObject;

final readonly class Product
{
    public function __construct(
        public string $selector,
        public int $priceCents,
        public int $stock,
    ) {
    }
}
