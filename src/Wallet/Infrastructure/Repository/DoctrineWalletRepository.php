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
        return $this->findWalletById($walletId, false);
    }

    public function findByIdForUpdate(WalletId $walletId): Wallet
    {
        return $this->findWalletById($walletId, true);
    }

    private function findWalletById(WalletId $walletId, bool $forUpdate): Wallet
    {
        $lockClause = $forUpdate ? ' FOR UPDATE' : '';

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT id, inserted_balance_cents FROM wallets WHERE id = :id%s', $lockClause),
            ['id' => $walletId->value()],
        );

        if ($row === false) {
            throw new WalletNotFoundException($walletId->value());
        }

        $coinRows = $this->connection->fetchAllAssociative(
            sprintf('SELECT coin_cents, coin_count FROM wallet_inserted_coins WHERE wallet_id = :wallet_id ORDER BY coin_cents ASC%s', $lockClause),
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

    public function create(Wallet $wallet): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('wallets', [
            'id' => $wallet->walletId()->value(),
            'inserted_balance_cents' => $wallet->balance()->cents(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    }

    public function update(Wallet $wallet): void
    {
        $this->connection->transactional(function () use ($wallet): void {
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
        });
    }
}
