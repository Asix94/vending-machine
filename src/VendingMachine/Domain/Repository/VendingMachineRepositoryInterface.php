<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Repository;

use App\VendingMachine\Domain\ValueObject\Product;

interface VendingMachineRepositoryInterface
{
    public function findProductBySelector(string $selector): Product;

    /**
     * @return array<int, int>
     */
    public function getMachineCoins(): array;

    /**
     * @param array<int, int> $machineCoins
     */
    public function updateMachineState(string $selector, int $newStock, array $machineCoins): void;
}
