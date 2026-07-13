<?php

declare(strict_types=1);

namespace App\Wallet\Application;

use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;

final readonly class AddMoneyUseCase
{
    public function __construct(private WalletRepositoryInterface $walletRepository)
    {
    }

    public function __invoke(Wallet $wallet, Money $money): void
    {
        $wallet->addMoney($money);
        $this->walletRepository->update($wallet);
    }
}
