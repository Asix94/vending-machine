<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Repository;

use App\VendingMachine\Domain\ValueObject\Product;

interface VendingMachineRepositoryInterface
{
    public function findProductBySelector(string $selector): Product;

    /**
     * @return list<array{selector:string, price_cents:int, stock:int}>
     */
    public function getAllProducts(): array;

    /**
     * @return array<int, int>
     */
    public function getMachineCoins(): array;

    /**
     * @param array<int, int> $machineCoins
     */
    public function updateMachineState(string $selector, int $newStock, array $machineCoins): void;

    /**
     * @param array<string, int> $productStocks
     * @param array<int, int> $machineCoins
     */
    public function replaceServiceState(array $productStocks, array $machineCoins): void;

    /**
     * @param array<string, int> $productIncrements
     */
    public function incrementProductStocks(array $productIncrements): void;

    /**
     * @param array<int, int> $coinIncrements
     */
    public function incrementMachineCoins(array $coinIncrements): void;
}
