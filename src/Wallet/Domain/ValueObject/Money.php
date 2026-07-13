<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidMoneyAmountException;

final class Money
{
    public const ACCEPTED_VALUES = [5, 10, 25, 100];

    private int $amount;

    private function __construct(int $amount)
    {
        if (!in_array($amount, self::ACCEPTED_VALUES, true)) {
            throw new InvalidMoneyAmountException('Invalid coin amount. Accepted values are 5, 10, 25, 100 cents.');
        }

        $this->amount = $amount;
    }

    public static function fromCents(int $amount): self
    {
        return new self($amount);
    }

    public static function fromDecimal(float $amount): self
    {
        $cents = (int) round($amount * 100);

        return new self($cents);
    }

    public function cents(): int
    {
        return $this->amount;
    }
}
