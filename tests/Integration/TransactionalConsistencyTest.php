<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Infrastructure\Database\DbalTransactionManager;
use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Service\ExactChangeCalculator;
use App\VendingMachine\Infrastructure\Repository\DoctrineVendingMachineRepository;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use App\Wallet\Infrastructure\Repository\DoctrineWalletRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransactionalConsistencyTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->resetState();
    }

    public function testBuyRollsBackMachineStateWhenWalletUpdateFails(): void
    {
        $walletId = '55555555-5555-4555-8555-555555555555';

        $this->setProductStock('WATER', 1);
        $this->setMachineCoins([5 => 0, 10 => 1, 25 => 1, 100 => 0]);
        $this->createWallet($walletId, [100 => 1]);

        $innerWalletRepository = new DoctrineWalletRepository($this->connection);
        $failingWalletRepository = new class ($innerWalletRepository) implements WalletRepositoryInterface {
            public function __construct(private readonly DoctrineWalletRepository $repository)
            {
            }

            public function findById(WalletId $walletId): Wallet
            {
                return $this->repository->findById($walletId);
            }

            public function findByIdForUpdate(WalletId $walletId): Wallet
            {
                return $this->repository->findByIdForUpdate($walletId);
            }

            public function create(Wallet $wallet): void
            {
                $this->repository->create($wallet);
            }

            public function update(Wallet $wallet): void
            {
                throw new \RuntimeException('Simulated failure on wallet update.');
            }
        };

        $useCase = new BuyProductUseCase(
            $failingWalletRepository,
            new DoctrineVendingMachineRepository($this->connection),
            new DbalTransactionManager($this->connection),
            new ExactChangeCalculator(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated failure on wallet update.');

        try {
            $useCase(new BuyProductRequest($walletId, 'WATER'));
        } finally {
            self::assertSame(1, $this->productStock('WATER'));
            self::assertSame(100, $this->walletBalance($walletId));
            self::assertSame(1, $this->walletCoinCount($walletId, 100));
            self::assertSame(1, $this->machineCoinCount(10));
            self::assertSame(1, $this->machineCoinCount(25));
            self::assertSame(0, $this->machineCoinCount(100));
        }
    }

    public function testBuyRollsBackWhenExactChangeCannotBeMade(): void
    {
        $walletId = '66666666-6666-4666-8666-666666666666';

        $this->setProductStock('WATER', 1);
        $this->setMachineCoins([5 => 0, 10 => 0, 25 => 0, 100 => 0]);
        $this->createWallet($walletId, [100 => 1]);

        $useCase = new BuyProductUseCase(
            new DoctrineWalletRepository($this->connection),
            new DoctrineVendingMachineRepository($this->connection),
            new DbalTransactionManager($this->connection),
            new ExactChangeCalculator(),
        );

        $this->expectException(CannotMakeExactChangeException::class);

        try {
            $useCase(new BuyProductRequest($walletId, 'WATER'));
        } finally {
            self::assertSame(1, $this->productStock('WATER'));
            self::assertSame(100, $this->walletBalance($walletId));
            self::assertSame(1, $this->walletCoinCount($walletId, 100));
            self::assertSame(0, $this->machineCoinCount(100));
        }
    }

    private function resetState(): void
    {
        $this->connection->executeStatement('DELETE FROM wallet_inserted_coins');
        $this->connection->executeStatement('DELETE FROM wallets');
        $this->connection->executeStatement('UPDATE machine_products SET stock = 0');
        $this->connection->executeStatement('UPDATE machine_coins SET coin_count = 0');
    }

    /**
     * @param array<int, int> $coins
     */
    private function createWallet(string $walletId, array $coins): void
    {
        $balance = 0;
        foreach ($coins as $coinCents => $count) {
            $balance += $coinCents * $count;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('wallets', [
            'id' => $walletId,
            'inserted_balance_cents' => $balance,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($coins as $coinCents => $count) {
            if ($count <= 0) {
                continue;
            }

            $this->connection->insert('wallet_inserted_coins', [
                'wallet_id' => $walletId,
                'coin_cents' => $coinCents,
                'coin_count' => $count,
            ]);
        }
    }

    /**
     * @param array<int, int> $coins
     */
    private function setMachineCoins(array $coins): void
    {
        foreach ($coins as $coinCents => $count) {
            $this->connection->update('machine_coins', ['coin_count' => $count], ['coin_cents' => $coinCents]);
        }
    }

    private function setProductStock(string $selector, int $stock): void
    {
        $this->connection->update('machine_products', ['stock' => $stock], ['selector' => $selector]);
    }

    private function productStock(string $selector): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT stock FROM machine_products WHERE selector = :selector',
            ['selector' => $selector],
        );
    }

    private function walletBalance(string $walletId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId],
        );
    }

    private function walletCoinCount(string $walletId, int $coinCents): int
    {
        $value = $this->connection->fetchOne(
            'SELECT coin_count FROM wallet_inserted_coins WHERE wallet_id = :wallet_id AND coin_cents = :coin_cents',
            [
                'wallet_id' => $walletId,
                'coin_cents' => $coinCents,
            ],
        );

        if ($value === false) {
            return 0;
        }

        return (int) $value;
    }

    private function machineCoinCount(int $coinCents): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT coin_count FROM machine_coins WHERE coin_cents = :coin_cents',
            ['coin_cents' => $coinCents],
        );
    }
}
