<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\Dto\ServiceMachineRequest;
use App\VendingMachine\Application\Dto\ServiceMachineResponse;
use App\VendingMachine\Application\Dto\ServiceProductsRequest;
use InvalidArgumentException;

final readonly class ServiceVendingMachineUseCase
{
    public function __construct(
        private ServiceProductsUseCase $serviceProductsUseCase,
        private ServiceCoinsUseCase $serviceCoinsUseCase,
    ) {
    }

    public function __invoke(ServiceMachineRequest $request): ServiceMachineResponse
    {
        if ($request->products === [] || $request->coins === []) {
            throw new InvalidArgumentException('Fields "products" and "coins" are required and must be non-empty arrays.');
        }

        $productsRequest = new ServiceProductsRequest($this->mapLegacyProducts($request->products));
        $this->serviceProductsUseCase->__invoke($productsRequest);

        $coinsRequest = new ServiceCoinsRequest($this->mapLegacyCoins($request->coins));

        return $this->serviceCoinsUseCase->__invoke($coinsRequest);
    }

    /**
     * @param list<array{selector:string, stock:int}> $products
     *
     * @return list<array{selector:string, quantity_to_add:int}>
     */
    private function mapLegacyProducts(array $products): array
    {
        $mapped = [];

        foreach ($products as $product) {
            $mapped[] = [
                'selector' => $product['selector'] ?? '',
                'quantity_to_add' => $product['stock'] ?? null,
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, int> $coins
     *
     * @return list<array{coin:string, quantity_to_add:int}>
     */
    private function mapLegacyCoins(array $coins): array
    {
        $mapped = [];

        foreach ($coins as $coin => $count) {
            $mapped[] = [
                'coin' => (string) $coin,
                'quantity_to_add' => $count,
            ];
        }

        return $mapped;
    }
}
