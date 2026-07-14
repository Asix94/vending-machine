<?php

declare(strict_types=1);

namespace App\Wallet\Application\Dto;

final readonly class AddMoneyResponse
{
    /**
     * @param array<string, int> $insertedCoins
     */
    public function __construct(
        public string $walletId,
        public float $insertedBalance,
        public array $insertedCoins,
    ) {
    }

    public function toArray(): array
    {
        return [
            'wallet_id' => $this->walletId,
            'inserted_balance' => $this->insertedBalance,
            'inserted_coins' => $this->insertedCoins,
        ];
    }
}
