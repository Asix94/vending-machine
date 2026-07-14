<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Exception;

use DomainException;

final class InsufficientFundsException extends DomainException
{
    public function __construct(int $requiredCents, int $providedCents)
    {
        parent::__construct(sprintf('Insufficient funds: required %d cents, provided %d cents.', $requiredCents, $providedCents));
    }
}
