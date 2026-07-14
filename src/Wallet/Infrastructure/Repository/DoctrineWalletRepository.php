<?php

declare(strict_types=1);

namespace App\Wallet\Infrastructure\Repository;

use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\WalletId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DoctrineWalletRepository implements WalletRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findById(WalletId $walletId): Wallet
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId->value()],
        );

        if ($row === false) {
            throw new WalletNotFoundException($walletId->value());
        }

        $coinRows = $this->connection->fetchAllAssociative(
            'SELECT coin_cents, coin_count FROM wallet_inserted_coins WHERE wallet_id = :wallet_id',
            ['wallet_id' => $walletId->value()],
        );

        $insertedCoins = [];
        foreach ($coinRows as $coinRow) {
            $insertedCoins[(int) $coinRow['coin_cents']] = (int) $coinRow['coin_count'];
        }

        return new Wallet(
            new WalletId((string) $row['id']),
            new Balance((int) $row['inserted_balance_cents']),
            $insertedCoins,
        );
    }

    public function create(Wallet $wallet): Wallet
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('wallets', [
            'id' => $wallet->walletId()->value(),
            'inserted_balance_cents' => $wallet->balance()->cents(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $wallet;
    }

    public function update(Wallet $wallet): void
    {
        $this->connection->beginTransaction();

        try {
            $updatedRows = $this->connection->update(
                'wallets',
                [
                    'inserted_balance_cents' => $wallet->balance()->cents(),
                    'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
                [
                    'id' => $wallet->walletId()->value(),
                ],
            );

            if ($updatedRows === 0) {
                throw new WalletNotFoundException($wallet->walletId()->value());
            }

            $this->connection->executeStatement(
                'DELETE FROM wallet_inserted_coins WHERE wallet_id = :wallet_id',
                ['wallet_id' => $wallet->walletId()->value()],
            );

            foreach ($wallet->insertedCoins() as $coinCents => $count) {
                if ($count <= 0) {
                    continue;
                }

                $this->connection->insert('wallet_inserted_coins', [
                    'wallet_id' => $wallet->walletId()->value(),
                    'coin_cents' => $coinCents,
                    'coin_count' => $count,
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
}
