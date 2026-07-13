<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Repository;

use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\ValueObject\WalletId;

interface WalletRepositoryInterface
{
    public function findById(WalletId $walletId): Wallet;

    public function create(Wallet $wallet): Wallet;

    public function update(Wallet $wallet): void;
}
