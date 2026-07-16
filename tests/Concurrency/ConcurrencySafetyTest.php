<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use App\Shared\Infrastructure\Database\DbalTransactionManager;
use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Service\ExactChangeCalculator;
use App\VendingMachine\Infrastructure\Repository\DoctrineVendingMachineRepository;
use App\Wallet\Application\AddMoneyUseCase;
use App\Wallet\Application\Dto\AddMoneyRequest;
use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\ReturnCoinUseCase;
use App\Wallet\Infrastructure\Repository\DoctrineWalletRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ConcurrencySafetyTest extends KernelTestCase
{
    private Connection $primary;
    private Connection $secondary;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();

        $this->primary = static::getContainer()->get(Connection::class);
        $this->secondary = DriverManager::getConnection($this->primary->getParams());

        $this->resetState();
    }

    protected function tearDown(): void
    {
        if ($this->secondary->isConnected()) {
            $this->secondary->close();
        }

        parent::tearDown();
    }

    public function testConcurrentBuyOnLastStockEndsWithSingleSuccessfulPurchase(): void
    {
        $this->setProductStock('JUICE', 1);
        $this->createWallet('11111111-1111-4111-8111-111111111111', [100 => 1]);
        $this->createWallet('22222222-2222-4222-8222-222222222222', [100 => 1]);

        $buyPrimary = $this->buyUseCase($this->primary);
        $buySecondary = $this->buyUseCase($this->secondary);

        $this->primary->beginTransaction();
        $buyPrimary(new BuyProductRequest('11111111-1111-4111-8111-111111111111', 'JUICE'));

        $this->secondary->executeStatement("SET lock_timeout TO '200ms'");

        try {
            $buySecondary(new BuyProductRequest('22222222-2222-4222-8222-222222222222', 'JUICE'));
            self::fail('Expected lock wait timeout while first purchase transaction is open.');
        } catch (DriverException $exception) {
            self::assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
        } finally {
            $this->primary->commit();
        }

        try {
            $buySecondary(new BuyProductRequest('22222222-2222-4222-8222-222222222222', 'JUICE'));
            self::fail('Expected out_of_stock after first purchase is committed.');
        } catch (OutOfStockException) {
            self::assertSame(0, $this->productStock('JUICE'));
            self::assertSame(0, $this->walletBalance('11111111-1111-4111-8111-111111111111'));
            self::assertSame(100, $this->walletBalance('22222222-2222-4222-8222-222222222222'));
        }
    }

    public function testConcurrentInsertMoneyOnSameWalletDoesNotLoseUpdates(): void
    {
        $walletId = '33333333-3333-4333-8333-333333333333';
        $this->createWallet($walletId, []);

        $addPrimary = $this->addMoneyUseCase($this->primary);
        $addSecondary = $this->addMoneyUseCase($this->secondary);

        $this->primary->beginTransaction();
        $addPrimary(new AddMoneyRequest($walletId, [1.0]));

        $this->secondary->executeStatement("SET lock_timeout TO '200ms'");

        try {
            $addSecondary(new AddMoneyRequest($walletId, [0.25]));
            self::fail('Expected lock wait timeout while first insert-money transaction is open.');
        } catch (DriverException $exception) {
            self::assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
        } finally {
            $this->primary->commit();
        }

        $addSecondary(new AddMoneyRequest($walletId, [0.25]));

        self::assertSame(125, $this->walletBalance($walletId));
        self::assertSame(1, $this->walletCoinCount($walletId, 25));
        self::assertSame(1, $this->walletCoinCount($walletId, 100));
    }

    public function testConcurrentReturnCoinWithBuyKeepsWalletAndStockConsistent(): void
    {
        $walletId = '44444444-4444-4444-8444-444444444444';

        $this->setProductStock('JUICE', 1);
        $this->createWallet($walletId, [100 => 1]);

        $buyPrimary = $this->buyUseCase($this->primary);
        $returnSecondary = $this->returnCoinUseCase($this->secondary);

        $this->primary->beginTransaction();
        $buyPrimary(new BuyProductRequest($walletId, 'JUICE'));

        $this->secondary->executeStatement("SET lock_timeout TO '200ms'");

        try {
            $returnSecondary(new ReturnCoinRequest($walletId));
            self::fail('Expected lock wait timeout while buy transaction is open.');
        } catch (DriverException $exception) {
            self::assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
        } finally {
            $this->primary->commit();
        }

        $response = $returnSecondary(new ReturnCoinRequest($walletId));

        self::assertSame([], $response->returnedCoins);
        self::assertSame(0.0, $response->returnedTotal);
        self::assertSame(0, $this->walletBalance($walletId));
        self::assertSame(0, $this->productStock('JUICE'));
    }

    private function buyUseCase(Connection $connection): BuyProductUseCase
    {
        return new BuyProductUseCase(
            new DoctrineWalletRepository($connection),
            new DoctrineVendingMachineRepository($connection),
            new DbalTransactionManager($connection),
            new ExactChangeCalculator(),
        );
    }

    private function addMoneyUseCase(Connection $connection): AddMoneyUseCase
    {
        return new AddMoneyUseCase(
            new DoctrineWalletRepository($connection),
            new DbalTransactionManager($connection),
        );
    }

    private function returnCoinUseCase(Connection $connection): ReturnCoinUseCase
    {
        return new ReturnCoinUseCase(
            new DoctrineWalletRepository($connection),
            new DbalTransactionManager($connection),
        );
    }

    private function resetState(): void
    {
        $this->primary->executeStatement('DELETE FROM wallet_inserted_coins');
        $this->primary->executeStatement('DELETE FROM wallets');
        $this->primary->executeStatement('UPDATE machine_products SET stock = 0');
        $this->primary->executeStatement('UPDATE machine_coins SET coin_count = 0');
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

        $this->primary->insert('wallets', [
            'id' => $walletId,
            'inserted_balance_cents' => $balance,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        foreach ($coins as $coinCents => $count) {
            if ($count <= 0) {
                continue;
            }

            $this->primary->insert('wallet_inserted_coins', [
                'wallet_id' => $walletId,
                'coin_cents' => $coinCents,
                'coin_count' => $count,
            ]);
        }
    }

    private function setProductStock(string $selector, int $stock): void
    {
        $this->primary->update('machine_products', ['stock' => $stock], ['selector' => $selector]);
    }

    private function productStock(string $selector): int
    {
        return (int) $this->primary->fetchOne(
            'SELECT stock FROM machine_products WHERE selector = :selector',
            ['selector' => $selector],
        );
    }

    private function walletBalance(string $walletId): int
    {
        return (int) $this->primary->fetchOne(
            'SELECT inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId],
        );
    }

    private function walletCoinCount(string $walletId, int $coinCents): int
    {
        $value = $this->primary->fetchOne(
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
}
