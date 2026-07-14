<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class BuyProductResponse
{
    /**
     * @param list<float> $change
     */
    public function __construct(
        public string $selector,
        public float $price,
        public array $change,
        public float $walletBalanceAfter,
    ) {
    }

    public function toArray(): array
    {
        return [
            'item' => [
                'selector' => $this->selector,
                'price' => $this->price,
            ],
            'change' => $this->change,
            'wallet_balance_after' => $this->walletBalanceAfter,
        ];
    }
}
