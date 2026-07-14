<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class ServiceMachineRequest
{
    /**
     * @param list<array{selector:string, stock:int}> $products
     * @param array<string, int> $coins
     */
    public function __construct(
        public array $products,
        public array $coins,
    ) {
    }
}
