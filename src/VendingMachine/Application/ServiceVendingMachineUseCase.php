<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\Shared\Application\TransactionManagerInterface;
use App\VendingMachine\Application\Dto\ServiceMachineRequest;
use App\VendingMachine\Application\Dto\ServiceMachineResponse;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\Wallet\Domain\ValueObject\Money;

final readonly class ServiceVendingMachineUseCase
{
    private const ALLOWED_SELECTORS = ['WATER', 'JUICE', 'SODA'];
    private const SELECTOR_PRICES = [
        'WATER' => 65,
        'JUICE' => 100,
        'SODA' => 150,
    ];

    public function __construct(
        private VendingMachineRepositoryInterface $vendingMachineRepository,
        private TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(ServiceMachineRequest $request): ServiceMachineResponse
    {
        $normalizedProductStocks = $this->normalizeProductStocks($request->products);
        $normalizedCoins = $this->normalizeCoins($request->coins);

        return $this->transactionManager->run(function () use ($normalizedProductStocks, $normalizedCoins): ServiceMachineResponse {
            $this->vendingMachineRepository->replaceServiceState($normalizedProductStocks, $normalizedCoins);

            $products = $this->vendingMachineRepository->getAllProducts();
            $coins = $this->vendingMachineRepository->getMachineCoins();

            return new ServiceMachineResponse(
                $this->formatProductsForApi($products),
                $this->formatCoinsForApi($coins),
            );
        });
    }

    /**
     * @param list<array{selector:string, stock:int}> $products
     *
     * @return array<string, int>
     */
    private function normalizeProductStocks(array $products): array
    {
        $normalized = array_fill_keys(self::ALLOWED_SELECTORS, 0);

        foreach ($products as $product) {
            $selector = strtoupper($product['selector'] ?? '');
            $stock = $product['stock'] ?? null;

            if (!in_array($selector, self::ALLOWED_SELECTORS, true)) {
                throw new \InvalidArgumentException('Invalid selector. Allowed values are WATER, JUICE, SODA.');
            }

            if (!is_int($stock) || $stock < 0) {
                throw new \InvalidArgumentException('Product stock must be a non-negative integer.');
            }

            $normalized[$selector] = $stock;
        }

        return $normalized;
    }

    /**
     * @param array<string, int> $coins
     *
     * @return array<int, int>
     */
    private function normalizeCoins(array $coins): array
    {
        $normalized = array_fill_keys(Money::ACCEPTED_VALUES, 0);

        foreach ($coins as $coin => $count) {
            if (!is_int($count) || $count < 0) {
                throw new \InvalidArgumentException('Coin count must be a non-negative integer.');
            }

            if (!is_string($coin) || !is_numeric($coin)) {
                throw new \InvalidArgumentException('Coin key must be numeric and use accepted denominations.');
            }

            $coinCents = (int) round(((float) $coin) * 100);

            if (!in_array($coinCents, Money::ACCEPTED_VALUES, true)) {
                throw new \InvalidArgumentException('Invalid coin denomination. Allowed values are 0.05, 0.10, 0.25, 1.00.');
            }

            $normalized[$coinCents] = $count;
        }

        return $normalized;
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
            $selector = $product['selector'];

            $formatted[] = [
                'selector' => $selector,
                'price' => (self::SELECTOR_PRICES[$selector] ?? $product['price_cents']) / 100,
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
