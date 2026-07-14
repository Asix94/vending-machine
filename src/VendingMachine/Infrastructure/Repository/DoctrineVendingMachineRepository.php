<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Repository;

use App\VendingMachine\Domain\Exception\ProductNotFoundException;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\VendingMachine\Domain\ValueObject\Product;
use App\Wallet\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineVendingMachineRepository implements VendingMachineRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findProductBySelector(string $selector): Product
    {
        $row = $this->connection->fetchAssociative(
            'SELECT selector, price_cents, stock FROM machine_products WHERE selector = :selector',
            ['selector' => strtoupper($selector)],
        );

        if ($row === false) {
            throw new ProductNotFoundException($selector);
        }

        return new Product(
            (string) $row['selector'],
            (int) $row['price_cents'],
            (int) $row['stock'],
        );
    }

    /**
     * @return list<array{selector:string, price_cents:int, stock:int}>
     */
    public function getAllProducts(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT selector, price_cents, stock FROM machine_products ORDER BY selector ASC',
        );

        return array_map(
            static fn (array $row): array => [
                'selector' => (string) $row['selector'],
                'price_cents' => (int) $row['price_cents'],
                'stock' => (int) $row['stock'],
            ],
            $rows,
        );
    }

    /**
     * @return array<int, int>
     */
    public function getMachineCoins(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT coin_cents, coin_count FROM machine_coins ORDER BY coin_cents ASC',
        );

        $coins = array_fill_keys(Money::ACCEPTED_VALUES, 0);

        foreach ($rows as $row) {
            $coins[(int) $row['coin_cents']] = (int) $row['coin_count'];
        }

        return $coins;
    }

    /**
     * @param array<int, int> $machineCoins
     */
    public function updateMachineState(string $selector, int $newStock, array $machineCoins): void
    {
        $updatedRows = $this->connection->update(
            'machine_products',
            [
                'stock' => $newStock,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            ['selector' => strtoupper($selector)],
        );

        if ($updatedRows === 0) {
            throw new ProductNotFoundException($selector);
        }

        foreach ($machineCoins as $coinCents => $coinCount) {
            $this->connection->update(
                'machine_coins',
                [
                    'coin_count' => $coinCount,
                    'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
                [
                    'coin_cents' => $coinCents,
                ],
            );
        }
    }

    /**
     * @param array<string, int> $productStocks
     * @param array<int, int> $machineCoins
     */
    public function replaceServiceState(array $productStocks, array $machineCoins): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($productStocks as $selector => $stock) {
            $updatedRows = $this->connection->update(
                'machine_products',
                [
                    'stock' => $stock,
                    'updated_at' => $now,
                ],
                ['selector' => strtoupper($selector)],
            );

            if ($updatedRows === 0) {
                throw new ProductNotFoundException($selector);
            }
        }

        foreach ($machineCoins as $coinCents => $coinCount) {
            $this->connection->update(
                'machine_coins',
                [
                    'coin_count' => $coinCount,
                    'updated_at' => $now,
                ],
                ['coin_cents' => $coinCents],
            );
        }
    }
}
