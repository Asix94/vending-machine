<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Application\Dto\BuyProductResponse;
use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Exception\InsufficientFundsException;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final readonly class BuyProductUseCase
{
    private const ALLOWED_SELECTORS = ['WATER', 'JUICE', 'SODA'];

    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private VendingMachineRepositoryInterface $vendingMachineRepository,
        private TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(BuyProductRequest $request): BuyProductResponse
    {
        $selector = strtoupper($request->product);
        if (!in_array($selector, self::ALLOWED_SELECTORS, true)) {
            throw new \InvalidArgumentException('Invalid selector. Allowed values are WATER, JUICE, SODA.');
        }

        return $this->transactionManager->run(function () use ($request, $selector): BuyProductResponse {
            $wallet = $this->walletRepository->findById(new WalletId($request->walletId));
            $product = $this->vendingMachineRepository->findProductBySelector($selector);

            if ($product->stock <= 0) {
                throw new OutOfStockException($selector);
            }

            $walletBalance = $wallet->balance()->cents();
            if ($walletBalance < $product->priceCents) {
                throw new InsufficientFundsException($product->priceCents, $walletBalance);
            }

            $machineCoins = $this->vendingMachineRepository->getMachineCoins();
            $machineCoinsAfterWalletTransfer = $this->addWalletCoinsToMachine($machineCoins, $wallet->insertedCoins());
            $changeCents = $walletBalance - $product->priceCents;
            $changeCoins = $this->calculateExactChange($changeCents, $machineCoinsAfterWalletTransfer);

            $machineCoinsAfterChange = $this->subtractMachineCoins($machineCoinsAfterWalletTransfer, $changeCoins);
            $walletAfterPurchase = $wallet->withdrawAll();

            $this->vendingMachineRepository->updateMachineState($selector, $product->stock - 1, $machineCoinsAfterChange);
            $this->walletRepository->update($walletAfterPurchase);

            return new BuyProductResponse(
                $selector,
                $product->priceCents / 100,
                $this->expandCoinsForApi($changeCoins),
                $walletAfterPurchase->balance()->toDecimal(),
            );
        });
    }

    /**
     * @param array<int, int> $machineCoins
     * @param array<int, int> $walletCoins
     *
     * @return array<int, int>
     */
    private function addWalletCoinsToMachine(array $machineCoins, array $walletCoins): array
    {
        $result = $machineCoins;

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $result[$coin] = ($result[$coin] ?? 0) + ($walletCoins[$coin] ?? 0);
        }

        return $result;
    }

    /**
     * @param array<int, int> $availableCoins
     *
     * @return array<int, int>
     */
    private function calculateExactChange(int $changeCents, array $availableCoins): array
    {
        $remaining = $changeCents;
        $usedCoins = array_fill_keys(Money::ACCEPTED_VALUES, 0);

        if ($remaining === 0) {
            return $usedCoins;
        }

        $orderedCoins = Money::ACCEPTED_VALUES;
        rsort($orderedCoins);

        foreach ($orderedCoins as $coin) {
            if ($remaining <= 0) {
                break;
            }

            $maxNeeded = intdiv($remaining, $coin);
            $available = $availableCoins[$coin] ?? 0;
            $take = min($maxNeeded, $available);

            if ($take > 0) {
                $usedCoins[$coin] = $take;
                $remaining -= $take * $coin;
            }
        }

        if ($remaining !== 0) {
            throw new CannotMakeExactChangeException($changeCents);
        }

        return $usedCoins;
    }

    /**
     * @param array<int, int> $machineCoins
     * @param array<int, int> $changeCoins
     *
     * @return array<int, int>
     */
    private function subtractMachineCoins(array $machineCoins, array $changeCoins): array
    {
        $result = $machineCoins;

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $result[$coin] = ($result[$coin] ?? 0) - ($changeCoins[$coin] ?? 0);
        }

        return $result;
    }

    /**
     * @param array<int, int> $coins
     *
     * @return list<float>
     */
    private function expandCoinsForApi(array $coins): array
    {
        $expanded = [];

        $orderedCoins = Money::ACCEPTED_VALUES;
        rsort($orderedCoins);

        foreach ($orderedCoins as $coin) {
            $count = $coins[$coin] ?? 0;
            for ($index = 0; $index < $count; $index++) {
                $expanded[] = $coin / 100;
            }
        }

        return $expanded;
    }
}
