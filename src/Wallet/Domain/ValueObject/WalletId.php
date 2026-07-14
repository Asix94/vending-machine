<?php

declare(strict_types=1);

namespace App\Wallet\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use InvalidArgumentException;

final class WalletId
{
    private string $value;

    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Wallet ID must be a valid UUID.');
        }

        $this->value = $value;
    }

    public static function create(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
