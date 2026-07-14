<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class ServiceMachineResponse
{
    /**
     * @param list<array{selector:string, price:float, stock:int}> $products
     * @param array<string, int> $machineCoins
     */
    public function __construct(
        public array $products,
        public array $machineCoins,
    ) {
    }

    public function toArray(): array
    {
        return [
            'products' => $this->products,
            'machine_coins' => $this->machineCoins,
        ];
    }
}
