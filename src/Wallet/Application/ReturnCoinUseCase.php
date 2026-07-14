<?php

declare(strict_types=1);

namespace App\Wallet\Application;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\Dto\ReturnCoinResponse;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class ReturnCoinUseCase
{
    public function __construct(private WalletRepositoryInterface $walletRepository)
    {
    }

    public function __invoke(ReturnCoinRequest $request): ReturnCoinResponse
    {
        $wallet = $this->walletRepository->findById(new WalletId($request->walletId));
        $returnedCoins = $wallet->returnAllCoins();

        $this->walletRepository->update($wallet);

        return new ReturnCoinResponse(
            (string) $wallet->walletId(),
            $returnedCoins,
            array_sum($returnedCoins),
            $wallet->balance()->toDecimal(),
        );
    }
}
