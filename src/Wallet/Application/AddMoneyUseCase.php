<?php

declare(strict_types=1);

namespace App\Wallet\Application;

use App\Wallet\Application\Dto\AddMoneyRequest;
use App\Wallet\Application\Dto\AddMoneyResponse;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class AddMoneyUseCase
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionManagerInterface $transactionManager,
    )
    {
    }

    public function __invoke(AddMoneyRequest $request): AddMoneyResponse
    {
        return $this->transactionManager->run(function () use ($request): AddMoneyResponse {
            $wallet = $this->walletRepository->findByIdForUpdate(new WalletId($request->walletId));

            foreach ($request->coins as $coin) {
                $wallet->addMoney(Money::fromCanonicalDecimal($coin));
            }

            $this->walletRepository->update($wallet);

            return new AddMoneyResponse(
                $wallet->walletId()->value(),
                $wallet->balance()->toDecimal(),
                $this->formatCoinsForApi($wallet->insertedCoins()),
            );
        });
    }

    /**
     * @param array<int, int> $insertedCoins
     *
     * @return array<string, int>
     */
    private function formatCoinsForApi(array $insertedCoins): array
    {
        $formatted = [];

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $formatted[sprintf('%.2f', $coin / 100)] = $insertedCoins[$coin] ?? 0;
        }

        return $formatted;
    }
}
