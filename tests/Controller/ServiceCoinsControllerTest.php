<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ServiceCoinsControllerTest extends WebTestCase
{
    private Connection $connection;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->executeStatement('UPDATE machine_products SET stock = 0');
        $this->connection->executeStatement('UPDATE machine_coins SET coin_count = 0');
    }

    public function testServiceCoinsAddsCoinCountsToExistingValues(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/coins',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'coins' => [
                    ['coin' => '0.25', 'quantity_to_add' => 2],
                    ['coin' => '0.25', 'quantity_to_add' => 1],
                    ['coin' => '1.00', 'quantity_to_add' => 3],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('machine_coins', $payload);
        self::assertArrayNotHasKey('products', $payload);
        self::assertSame(3, $this->coinCount(25));
        self::assertSame(3, $this->coinCount(100));
        self::assertSame(0, $this->coinCount(5));
    }

    public function testServiceCoinsReturns400WhenCoinIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/coins',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'coins' => [
                    ['coin' => '0.20', 'quantity_to_add' => 1],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    public function testServiceCoinsRejectsRoundedEdgeCoinValues(): void
    {
        foreach (['0.049', '0.051', '0.099', '1.001'] as $coin) {
            $this->client->request(
                'POST',
                '/vending-machine/service/coins',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'coins' => [
                        ['coin' => $coin, 'quantity_to_add' => 1],
                    ],
                ], JSON_THROW_ON_ERROR),
            );

            self::assertResponseStatusCodeSame(400);
            self::assertSame('invalid_payload', $this->errorCode());
        }
    }

    public function testServiceCoinsReturns400WhenQuantityToAddIsNotPositive(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/coins',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'coins' => [
                    ['coin' => '0.25', 'quantity_to_add' => 0],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    private function coinCount(int $coinCents): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT coin_count FROM machine_coins WHERE coin_cents = :coin',
            ['coin' => $coinCents],
        );
    }

    private function errorCode(): string
    {
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (string) $payload['error'];
    }
}
