<?php

declare(strict_types=1);

namespace App\Wallet\Application\Dto;

final readonly class AddMoneyRequest
{
    /**
     * @param list<string> $coins
     */
    public function __construct(
        public string $walletId,
        public array $coins,
    ) {
    }
}
