<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class BuyProductRequest
{
    public function __construct(
        public string $walletId,
        public string $product,
    ) {
    }
}
