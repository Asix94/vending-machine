<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BuyProductControllerTest extends WebTestCase
{
    private Connection $connection;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->executeStatement('DELETE FROM wallet_inserted_coins');
        $this->connection->executeStatement('DELETE FROM wallets');

        $this->connection->executeStatement('UPDATE machine_products SET stock = 0');
        $this->connection->executeStatement('UPDATE machine_coins SET coin_count = 0');
    }

    public function testBuyProductWithExactChangeSucceeds(): void
    {
        $this->setProductStock('JUICE', 1);
        $walletId = $this->createWallet();
        $this->insertMoney($walletId, [1.0]);

        $this->client->request('POST', '/wallets/'.$walletId.'/buy/JUICE');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('JUICE', $payload['item']['selector']);
        self::assertSame(1.0, (float) $payload['item']['price']);
        self::assertSame([], $payload['change']);
        self::assertSame(0.0, (float) $payload['wallet_balance_after']);

        self::assertSame(0, $this->productStock('JUICE'));
        self::assertSame(1, $this->coinCount(100));
        self::assertSame(0, $this->walletBalance($walletId));
    }

    public function testBuyProductWithChangeSucceeds(): void
    {
        $this->setProductStock('WATER', 2);
        $this->setMachineCoins([
            25 => 1,
            10 => 1,
        ]);

        $walletId = $this->createWallet();
        $this->insertMoney($walletId, [1.0]);

        $this->client->request('POST', '/wallets/'.$walletId.'/buy/WATER');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('WATER', $payload['item']['selector']);
        self::assertSame([0.25, 0.1], array_map('floatval', $payload['change']));
        self::assertSame(0.35, (float) $payload['wallet_balance_after']);

        self::assertSame(1, $this->productStock('WATER'));
        self::assertSame(0, $this->coinCount(25));
        self::assertSame(0, $this->coinCount(10));
        self::assertSame(1, $this->coinCount(100));
        self::assertSame(35, $this->walletBalance($walletId));
    }

    public function testBuyProductReturns409WhenInsufficientFunds(): void
    {
        $this->setProductStock('SODA', 1);
        $walletId = $this->createWallet();
        $this->insertMoney($walletId, [0.25]);

        $this->client->request('POST', '/wallets/'.$walletId.'/buy/SODA');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            ['error' => 'insufficient_funds', 'message' => 'Insufficient funds to buy selected product.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testBuyProductReturns409WhenOutOfStock(): void
    {
        $this->setProductStock('SODA', 0);
        $walletId = $this->createWallet();
        $this->insertMoney($walletId, [1.0, 1.0]);

        $this->client->request('POST', '/wallets/'.$walletId.'/buy/SODA');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            ['error' => 'out_of_stock', 'message' => 'Selected product is out of stock.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testBuyProductReturns409AndRollsBackWhenCannotMakeExactChange(): void
    {
        $this->setProductStock('WATER', 2);

        $walletId = $this->createWallet();
        $this->insertMoney($walletId, [1.0]);

        $this->client->request('POST', '/wallets/'.$walletId.'/buy/WATER');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            ['error' => 'cannot_make_exact_change', 'message' => 'Cannot complete purchase because exact change is not available.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );

        self::assertSame(2, $this->productStock('WATER'));
        self::assertSame(0, $this->coinCount(5));
        self::assertSame(0, $this->coinCount(10));
        self::assertSame(0, $this->coinCount(25));
        self::assertSame(0, $this->coinCount(100));
        self::assertSame(100, $this->walletBalance($walletId));
    }

    private function createWallet(): string
    {
        $this->client->request('POST', '/wallets');
        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (string) $payload['wallet_id'];
    }

    /**
     * @param list<float> $coins
     */
    private function insertMoney(string $walletId, array $coins): void
    {
        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => $coins], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
    }

    private function setProductStock(string $selector, int $stock): void
    {
        $this->connection->update('machine_products', ['stock' => $stock], ['selector' => $selector]);
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

    private function productStock(string $selector): int
    {
        $value = $this->connection->fetchOne(
            'SELECT stock FROM machine_products WHERE selector = :selector',
            ['selector' => $selector],
        );

        return (int) $value;
    }

    private function coinCount(int $coinCents): int
    {
        $value = $this->connection->fetchOne(
            'SELECT coin_count FROM machine_coins WHERE coin_cents = :coin',
            ['coin' => $coinCents],
        );

        return (int) $value;
    }

    private function walletBalance(string $walletId): int
    {
        $value = $this->connection->fetchOne(
            'SELECT inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId],
        );

        return (int) $value;
    }
}
