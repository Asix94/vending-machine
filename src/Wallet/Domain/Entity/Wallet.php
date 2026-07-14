<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Entity;

use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;

final class Wallet
{
    private WalletId $walletId;
    private Balance $balance;
    /**
     * @var array<int, int>
     */
    private array $insertedCoins;

    /**
     * @param array<int, int> $insertedCoins
     */
    public function __construct(WalletId $walletId, Balance $balance, array $insertedCoins = [])
    {
        $this->walletId = $walletId;
        $this->balance = $balance;
        $this->insertedCoins = $this->normalizeInsertedCoins($insertedCoins);
    }

    public function walletId(): WalletId
    {
        return $this->walletId;
    }

    public function balance(): Balance
    {
        return $this->balance;
    }

    public function addMoney(Money $money): void
    {
        $this->balance = $this->balance->add($money);
        $coin = $money->cents();
        $this->insertedCoins[$coin]++;
    }

    /**
     * @return array<int, int>
     */
    public function insertedCoins(): array
    {
        return $this->insertedCoins;
    }

    public function withdrawAll(): self
    {
        return new self($this->walletId, new Balance(0));
    }

    /**
     * @param array<int, int> $insertedCoins
     *
     * @return array<int, int>
     */
    private function normalizeInsertedCoins(array $insertedCoins): array
    {
        $normalized = array_fill_keys(Money::ACCEPTED_VALUES, 0);

        foreach ($insertedCoins as $coin => $count) {
            $coinValue = (int) $coin;
            $countValue = (int) $count;

            if (!in_array($coinValue, Money::ACCEPTED_VALUES, true)) {
                continue;
            }

            if ($countValue < 0) {
                continue;
            }

            $normalized[$coinValue] = $countValue;
        }

        return $normalized;
    }
}
