<?php

declare(strict_types=1);

namespace App\VendingMachine\Application\Dto;

final readonly class ServiceCoinsRequest
{
    /**
     * @param list<array{coin:string, quantity_to_add:int}> $coins
     */
    public function __construct(public array $coins)
    {
    }
}
