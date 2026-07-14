<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class CannotMakeExactChangeException extends DomainException
{
    public function __construct(int $changeCents)
    {
        parent::__construct(sprintf('Cannot make exact change for %d cents.', $changeCents));
    }
}
