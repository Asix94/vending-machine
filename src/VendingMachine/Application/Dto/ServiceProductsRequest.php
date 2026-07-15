<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class ServiceProductsRequest
{
    /**
     * @param list<array{selector:string, quantity_to_add:int}> $products
     */
    public function __construct(public array $products)
    {
    }
}
