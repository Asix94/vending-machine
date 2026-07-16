<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

use App\Wallet\Domain\Exception\InvalidMoneyAmountException;

final class Money
{
    public const ACCEPTED_VALUES = [5, 10, 25, 100];
    private const ACCEPTED_DECIMALS = [
        '0.05' => 5,
        '0.10' => 10,
        '0.25' => 25,
        '1.00' => 100,
    ];

    private int $amount;

    private function __construct(int $amount)
    {
        if (!in_array($amount, self::ACCEPTED_VALUES, true)) {
            throw new InvalidMoneyAmountException('Invalid coin amount. Accepted values are 5, 10, 25, 100 cents.');
        }

        $this->amount = $amount;
    }

    public static function fromCanonicalDecimal(string $amount): self
    {
        return new self(self::toCentsFromCanonicalDecimal($amount));
    }

    public static function toCentsFromCanonicalDecimal(string $amount): int
    {
        if (!array_key_exists($amount, self::ACCEPTED_DECIMALS)) {
            throw new InvalidMoneyAmountException('Invalid coin amount. Accepted values are 0.05, 0.10, 0.25, 1.00.');
        }

        return self::ACCEPTED_DECIMALS[$amount];
    }

    public function cents(): int
    {
        return $this->amount;
    }
}
