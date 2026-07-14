<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateWalletControllerTest extends WebTestCase
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

    public function testCreateWalletReturns201AndPersistsWallet(): void
    {
        $this->client->request('POST', '/wallets');

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($response->headers->has('Location'));

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('wallet_id', $payload);
        self::assertArrayHasKey('inserted_balance', $payload);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $payload['wallet_id']);
        self::assertSame(0.0, (float) $payload['inserted_balance']);
        self::assertSame('/wallets/'.$payload['wallet_id'], $response->headers->get('Location'));

        $wallet = $this->connection->fetchAssociative(
            'SELECT id, inserted_balance_cents FROM wallets WHERE id = :id',
            ['id' => $payload['wallet_id']],
        );

        self::assertNotFalse($wallet);
        self::assertSame($payload['wallet_id'], $wallet['id']);
        self::assertSame(0, (int) $wallet['inserted_balance_cents']);
    }
}
