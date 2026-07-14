<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class OutOfStockException extends DomainException
{
    public function __construct(string $selector)
    {
        parent::__construct(sprintf('Product "%s" is out of stock.', $selector));
    }
}
