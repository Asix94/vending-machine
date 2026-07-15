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
    public function __construct(
        private VendingMachineRepositoryInterface $vendingMachineRepository,
        private TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(ServiceMachineRequest $request): ServiceMachineResponse
    {
        $productCatalog = $this->getProductCatalog();
        $normalizedProductStocks = $this->normalizeProductStocks($request->products, array_keys($productCatalog));
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
     * @param list<string> $allowedSelectors
     *
     * @return array<string, int>
     */
    private function normalizeProductStocks(array $products, array $allowedSelectors): array
    {
        $normalized = array_fill_keys($allowedSelectors, 0);

        foreach ($products as $product) {
            $selector = strtoupper($product['selector'] ?? '');
            $stock = $product['stock'] ?? null;

            if (!in_array($selector, $allowedSelectors, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid selector. Allowed values are %s.', implode(', ', $allowedSelectors)));
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
            $formatted[] = [
                'selector' => $product['selector'],
                'price' => $product['price_cents'] / 100,
                'stock' => $product['stock'],
            ];
        }

        return $formatted;
    }

    /**
     * @return array<string, int>
     */
    private function getProductCatalog(): array
    {
        $catalog = [];

        foreach ($this->vendingMachineRepository->getAllProducts() as $product) {
            $catalog[$product['selector']] = (int) $product['price_cents'];
        }

        ksort($catalog);

        return $catalog;
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
