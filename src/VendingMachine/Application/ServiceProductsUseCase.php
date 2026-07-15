<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\Shared\Application\TransactionManagerInterface;
use App\VendingMachine\Application\Dto\ServiceMachineResponse;
use App\VendingMachine\Application\Dto\ServiceProductsRequest;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use InvalidArgumentException;

final readonly class ServiceProductsUseCase
{
    public function __construct(
        private VendingMachineRepositoryInterface $vendingMachineRepository,
        private TransactionManagerInterface $transactionManager,
    ) {
    }

    public function __invoke(ServiceProductsRequest $request): ServiceMachineResponse
    {
        $catalog = $this->getProductCatalog();
        $increments = $this->normalizeProductIncrements($request->products, array_keys($catalog));

        return $this->transactionManager->run(function () use ($increments): ServiceMachineResponse {
            $this->vendingMachineRepository->incrementProductStocks($increments);

            return new ServiceMachineResponse(
                $this->formatProductsForApi($this->vendingMachineRepository->getAllProducts()),
                []
            );
        });
    }

    /**
     * @param list<array{product:string, quantity_to_add:int}> $products
     * @param list<string> $allowedSelectors
     *
     * @return array<string, int>
     */
    private function normalizeProductIncrements(array $products, array $allowedSelectors): array
    {
        if ($products === []) {
            throw new InvalidArgumentException('Field "products" is required and must be a non-empty array.');
        }

        $increments = [];

        foreach ($products as $product) {
            $selector = strtoupper((string) ($product['product'] ?? ''));
            $quantityToAdd = $product['quantity_to_add'] ?? null;

            if (!in_array($selector, $allowedSelectors, true)) {
                throw new InvalidArgumentException(sprintf('Invalid selector. Allowed values are %s.', implode(', ', $allowedSelectors)));
            }

            if (!is_int($quantityToAdd) || $quantityToAdd <= 0) {
                throw new InvalidArgumentException('quantity_to_add must be a positive integer for each product.');
            }

            $increments[$selector] = ($increments[$selector] ?? 0) + $quantityToAdd;
        }

        return $increments;
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
}
