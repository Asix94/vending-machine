<?php

declare(strict_types=1);

namespace App\VendingMachine\Application;

use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\Dto\ServiceMachineResponse;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\Wallet\Domain\Exception\InvalidMoneyAmountException;
use App\Wallet\Domain\ValueObject\Money;
use InvalidArgumentException;

final readonly class ServiceCoinsUseCase
{
    public function __construct(private VendingMachineRepositoryInterface $vendingMachineRepository)
    {
    }

    public function __invoke(ServiceCoinsRequest $request): ServiceMachineResponse
    {
        $increments = $this->normalizeCoinIncrements($request->coins);
        $this->vendingMachineRepository->incrementMachineCoins($increments);

        return new ServiceMachineResponse(
            [],
            $this->formatCoinsForApi($this->vendingMachineRepository->getMachineCoins()),
        );
    }

    /**
     * @param list<array{coin:string, quantity_to_add:int}> $coins
     *
     * @return array<int, int>
     */
    private function normalizeCoinIncrements(array $coins): array
    {
        if ($coins === []) {
            throw new InvalidArgumentException('Field "coins" is required and must be a non-empty array.');
        }

        $increments = [];

        foreach ($coins as $coinPayload) {
            $coin = $coinPayload['coin'] ?? null;
            $quantityToAdd = $coinPayload['quantity_to_add'] ?? null;

            if (!is_string($coin)) {
                throw new InvalidArgumentException('coin must be a canonical string and use accepted denominations.');
            }

            if (!is_int($quantityToAdd) || $quantityToAdd <= 0) {
                throw new InvalidArgumentException('quantity_to_add must be a positive integer for each coin.');
            }

            try {
                $coinCents = Money::toCentsFromCanonicalDecimal($coin);
            } catch (InvalidMoneyAmountException $exception) {
                throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
            }

            $increments[$coinCents] = ($increments[$coinCents] ?? 0) + $quantityToAdd;
        }

        return $increments;
    }

    /**
     * @param array<int, int> $coins
     *
     * @return array<string, int>
     */
    private function formatCoinsForApi(array $coins): array
    {
        $formatted = [];

        foreach (Money::ACCEPTED_VALUES as $coin) {
            $formatted[sprintf('%.2f', $coin / 100)] = $coins[$coin] ?? 0;
        }

        return $formatted;
    }
}
