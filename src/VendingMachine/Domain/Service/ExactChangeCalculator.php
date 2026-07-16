<?php

declare(strict_types=1);

namespace App\VendingMachine\Domain\Service;

use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\Wallet\Domain\ValueObject\Money;

final readonly class ExactChangeCalculator
{
    /**
     * @param array<int, int> $availableCoins
     *
     * @return array<int, int>
     */
    public function calculate(int $changeCents, array $availableCoins): array
    {
        $normalizedAvailableCoins = $this->normalizeCoins($availableCoins);
        $usedCoins = array_fill_keys(Money::ACCEPTED_VALUES, 0);

        if ($changeCents === 0) {
            return $usedCoins;
        }

        $orderedCoins = Money::ACCEPTED_VALUES;
        rsort($orderedCoins);

        $solution = $this->search($changeCents, $orderedCoins, $normalizedAvailableCoins, 0, $usedCoins);
        if ($solution === null) {
            throw new CannotMakeExactChangeException($changeCents);
        }

        return $solution;
    }

    /**
     * @param list<int> $orderedCoins
     * @param array<int, int> $availableCoins
     * @param array<int, int> $usedCoins
     *
     * @return array<int, int>|null
     */
    private function search(int $remaining, array $orderedCoins, array $availableCoins, int $index, array $usedCoins): ?array
    {
        if ($remaining === 0) {
            return $usedCoins;
        }

        if ($index >= count($orderedCoins)) {
            return null;
        }

        $coin = $orderedCoins[$index];
        $maxNeeded = intdiv($remaining, $coin);
        $available = $availableCoins[$coin] ?? 0;
        $maxTake = min($maxNeeded, $available);

        for ($take = $maxTake; $take >= 0; $take--) {
            $nextRemaining = $remaining - ($take * $coin);
            $nextUsedCoins = $usedCoins;
            $nextUsedCoins[$coin] = $take;

            $result = $this->search($nextRemaining, $orderedCoins, $availableCoins, $index + 1, $nextUsedCoins);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param array<int, int> $coins
     *
     * @return array<int, int>
     */
    private function normalizeCoins(array $coins): array
    {
        $normalized = [];

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $normalized[$coin] = max(0, $coins[$coin] ?? 0);
        }

        return $normalized;
    }
}
