<?php

declare(strict_types=1);

namespace App\Wallet\Application\Dto;

final readonly class ReturnCoinResponse
{
    /**
     * @param list<float> $returnedCoins
     */
    public function __construct(
        public string $walletId,
        public array $returnedCoins,
        public float $returnedTotal,
        public float $walletBalanceAfter,
    ) {
    }

    public function toArray(): array
    {
        return [
            'wallet_id' => $this->walletId,
            'returned_coins' => $this->returnedCoins,
            'returned_total' => $this->returnedTotal,
            'wallet_balance_after' => $this->walletBalanceAfter,
        ];
    }
}
