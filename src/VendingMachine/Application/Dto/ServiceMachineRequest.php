<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class ServiceMachineRequest
{
    /**
     * @param list<array{selector:string, quantity_to_add:int}> $products
     * @param list<array{coin:string, quantity_to_add:int}> $coins
     */
    public function __construct(
        public array $products = [],
        public array $coins = [],
    ) {
    }
}
