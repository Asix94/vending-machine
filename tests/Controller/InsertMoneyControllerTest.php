<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InsertMoneyControllerTest extends WebTestCase
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
    }

    public function testInsertMoneyAccumulatesBalanceAndCoins(): void
    {
        $walletId = $this->createWallet();

        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => ['0.25', '1.00', '0.10']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($walletId, $payload['wallet_id']);
        self::assertSame(1.35, (float) $payload['inserted_balance']);
        self::assertSame(
            [
                '0.05' => 0,
                '0.10' => 1,
                '0.25' => 1,
                '1.00' => 1,
            ],
            $payload['inserted_coins'],
        );

        $walletRow = $this->connection->fetchAssociative(
            'SELECT inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId],
        );

        self::assertNotFalse($walletRow);
        self::assertSame(135, (int) $walletRow['inserted_balance_cents']);

        $coins = $this->connection->fetchAllAssociative(
            'SELECT coin_cents, coin_count FROM wallet_inserted_coins WHERE wallet_id = :id ORDER BY coin_cents ASC',
            ['id' => $walletId],
        );

        self::assertSame(
            [
                ['coin_cents' => 10, 'coin_count' => 1],
                ['coin_cents' => 25, 'coin_count' => 1],
                ['coin_cents' => 100, 'coin_count' => 1],
            ],
            $coins,
        );
    }

    public function testInsertMoneyReturns404WhenWalletDoesNotExist(): void
    {
        $this->client->request(
            'POST',
            '/wallets/00000000-0000-4000-8000-000000000000/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => ['0.25']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            ['error' => 'wallet_not_found', 'message' => 'Wallet not found.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testInsertMoneyReturns400WhenPayloadIsInvalid(): void
    {
        $walletId = $this->createWallet();

        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => []], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'invalid_payload', 'message' => 'Field "coins" is required and must be a non-empty array.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testInsertMoneyReturns400WhenCoinIsInvalid(): void
    {
        $walletId = $this->createWallet();

        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => ['0.20']], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'invalid_money_amount', 'message' => 'Invalid coin amount. Accepted values are 0.05, 0.10, 0.25, 1.00.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testInsertMoneyRejectsNonCanonicalCoinFormats(): void
    {
        $walletId = $this->createWallet();

        foreach (['0.049', '0.051', '0.099', '1.001'] as $coin) {
            $this->client->request(
                'POST',
                '/wallets/'.$walletId.'/insert-money',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['coins' => [$coin]], JSON_THROW_ON_ERROR),
            );

            self::assertResponseStatusCodeSame(400);
            self::assertSame('invalid_money_amount', json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['error']);
        }
    }

    public function testInsertMoneyRejectsNumericCoinValues(): void
    {
        $walletId = $this->createWallet();

        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => [0.25]], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'invalid_payload', 'message' => 'Each coin value must be a canonical string.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function createWallet(): string
    {
        $this->client->request('POST', '/wallets');

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (string) $payload['wallet_id'];
    }
}
