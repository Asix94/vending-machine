<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReturnCoinControllerTest extends WebTestCase
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

    public function testReturnCoinReturnsInsertedCoinsAndResetsWalletState(): void
    {
        $walletId = $this->createWallet();
        $this->insertCoins($walletId, ['0.25', '1.00', '0.10']);

        $this->client->request('POST', '/wallets/'.$walletId.'/return-coin');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($walletId, $payload['wallet_id']);
        self::assertSame([1.0, 0.25, 0.1], array_map('floatval', $payload['returned_coins']));
        self::assertSame(1.35, (float) $payload['returned_total']);
        self::assertSame(0.0, (float) $payload['wallet_balance_after']);

        $walletRow = $this->connection->fetchAssociative(
            'SELECT inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $walletId],
        );

        self::assertNotFalse($walletRow);
        self::assertSame(0, (int) $walletRow['inserted_balance_cents']);

        $coinRows = $this->connection->fetchAllAssociative(
            'SELECT coin_cents, coin_count FROM wallet_inserted_coins WHERE wallet_id = :id',
            ['id' => $walletId],
        );

        self::assertSame([], $coinRows);
    }

    public function testReturnCoinReturnsEmptyResultWhenWalletHasNoCoins(): void
    {
        $walletId = $this->createWallet();

        $this->client->request('POST', '/wallets/'.$walletId.'/return-coin');

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($walletId, $payload['wallet_id']);
        self::assertSame([], $payload['returned_coins']);
        self::assertSame(0.0, (float) $payload['returned_total']);
        self::assertSame(0.0, (float) $payload['wallet_balance_after']);
    }

    public function testReturnCoinIsIdempotentAfterWalletHasBeenReset(): void
    {
        $walletId = $this->createWallet();
        $this->insertCoins($walletId, ['1.00', '0.25']);

        $this->client->request('POST', '/wallets/'.$walletId.'/return-coin');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/wallets/'.$walletId.'/return-coin');
        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($walletId, $payload['wallet_id']);
        self::assertSame([], $payload['returned_coins']);
        self::assertSame(0.0, (float) $payload['returned_total']);
        self::assertSame(0.0, (float) $payload['wallet_balance_after']);
    }

    public function testReturnCoinReturns404WhenWalletDoesNotExist(): void
    {
        $this->client->request('POST', '/wallets/00000000-0000-4000-8000-000000000000/return-coin');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            ['error' => 'wallet_not_found', 'message' => 'Wallet not found.'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testReturnCoinReturns400WhenWalletIdIsInvalid(): void
    {
        $this->client->request('POST', '/wallets/not-a-uuid/return-coin');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'invalid_wallet_id', 'message' => 'Wallet ID must be a valid UUID.'],
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

    /**
     * @param list<string> $coins
     */
    private function insertCoins(string $walletId, array $coins): void
    {
        $this->client->request(
            'POST',
            '/wallets/'.$walletId.'/insert-money',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['coins' => $coins], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
    }
}
