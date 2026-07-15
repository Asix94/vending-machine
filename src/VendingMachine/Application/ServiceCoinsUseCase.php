<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\Shared\Application\TransactionManagerInterface;
use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\Dto\ServiceMachineResponse;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;
use InvalidArgumentException;

final readonly class ServiceCoinsUseCase
{
    public function __construct(
        private VendingMachineRepositoryInterface $vendingMachineRepository,
        private TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(ServiceCoinsRequest $request): ServiceMachineResponse
    {
        $increments = $this->normalizeCoinIncrements($request->coins);

        return $this->transactionManager->run(function () use ($increments): ServiceMachineResponse {
            $this->vendingMachineRepository->incrementMachineCoins($increments);

            return new ServiceMachineResponse(
                $this->formatProductsForApi($this->vendingMachineRepository->getAllProducts()),
                $this->formatCoinsForApi($this->vendingMachineRepository->getMachineCoins()),
            );
        });
    }

    /**
     * @param list<array{coin:string, quantity_to_add:int}> $coins
     *
     * @return array<int, int>
     */
    private function normalizeCoinIncrements(array $coins): array
    {
        if ($coins === []) {
            throw new InvalidArgumentException('Field "coins" is required and must be a non-empty array.');
        }

        $increments = [];

        foreach ($coins as $coinPayload) {
            $coin = $coinPayload['coin'] ?? null;
            $quantityToAdd = $coinPayload['quantity_to_add'] ?? null;

            if (!is_string($coin) || !is_numeric($coin)) {
                throw new InvalidArgumentException('coin must be numeric and use accepted denominations.');
            }

            if (!is_int($quantityToAdd) || $quantityToAdd <= 0) {
                throw new InvalidArgumentException('quantity_to_add must be a positive integer for each coin.');
            }

            $coinCents = (int) round(((float) $coin) * 100);
            if (!in_array($coinCents, Money::ACCEPTED_VALUES, true)) {
                throw new InvalidArgumentException('Invalid coin denomination. Allowed values are 0.05, 0.10, 0.25, 1.00.');
            }

            $increments[$coinCents] = ($increments[$coinCents] ?? 0) + $quantityToAdd;
        }

        return $increments;
    }

    /**
     * @param list<array{selector:string, price_cents:int, stock:int}> $products
     *
     * @return list<array{selector:string, price:float, stock:int}>
     */
    private function formatProductsForApi(array $products): array
    {
        $formatted = [];

        foreach ($products as $product) {
            $formatted[] = [
                'selector' => $product['selector'],
                'price' => $product['price_cents'] / 100,
                'stock' => $product['stock'],
            ];
        }

        return $formatted;
    }

    /**
     * @param array<int, int> $coins
     *
     * @return array<string, int>
     */
    private function formatCoinsForApi(array $coins): array
    {
        $formatted = [];

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $formatted[sprintf('%.2f', $coin / 100)] = $coins[$coin] ?? 0;
        }

        return $formatted;
    }
}
