<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

use InvalidArgumentException;

final class Balance
{
    private int $amount;

    public function __construct(int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Balance cannot be negative.');
        }

        $this->amount = $amount;
    }

    public function add(Money $money): self
    {
        return new self($this->amount + $money->cents());
    }

    public function cents(): int
    {
        return $this->amount;
    }

    public function toDecimal(): float
    {
        return $this->amount / 100;
    }
}
