<?php

declare(strict_types=1);

namespace App\Wallet\Application;

use App\Wallet\Application\Dto\CreateWalletResponse;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class CreateWalletUseCase
{
    public function __construct(private WalletRepositoryInterface $walletRepository)
    {
    }

    public function __invoke(): CreateWalletResponse
    {
        $wallet = new Wallet(
            WalletId::create(),
            new Balance(0),
        );

        $createdWallet = $this->walletRepository->create($wallet);

        return new CreateWalletResponse(
            (string) $createdWallet->walletId(),
            $createdWallet->balance()->toDecimal(),
        );
    }
}
