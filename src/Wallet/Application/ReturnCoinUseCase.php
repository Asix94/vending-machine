<?php

declare(strict_types=1);

namespace App\Wallet\Application;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\Dto\ReturnCoinResponse;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class ReturnCoinUseCase
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionManagerInterface $transactionManager,
    )
    {
    }

    public function __invoke(ReturnCoinRequest $request): ReturnCoinResponse
    {
        return $this->transactionManager->run(function () use ($request): ReturnCoinResponse {
            $wallet = $this->walletRepository->findByIdForUpdate(new WalletId($request->walletId));
            $returnedTotal = $wallet->balance()->toDecimal();
            $returnedCoins = $wallet->returnAllCoins();

            $this->walletRepository->update($wallet);

            return new ReturnCoinResponse(
                $wallet->walletId()->value(),
                $returnedCoins,
                $returnedTotal,
                $wallet->balance()->toDecimal(),
            );
        });
    }
}
