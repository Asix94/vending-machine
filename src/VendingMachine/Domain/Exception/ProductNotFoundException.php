<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class ProductNotFoundException extends DomainException
{
    public function __construct(string $selector)
    {
        parent::__construct(sprintf('Product with selector "%s" was not found.', $selector));
    }
}
