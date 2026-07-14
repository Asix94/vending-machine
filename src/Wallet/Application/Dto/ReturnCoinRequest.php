<?php

declare(strict_types=1);

namespace App\Wallet\Application\Dto;

final readonly class ReturnCoinRequest
{
    public function __construct(public string $walletId)
    {
    }
}
