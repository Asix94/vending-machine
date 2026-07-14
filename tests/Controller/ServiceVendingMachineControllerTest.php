<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ServiceVendingMachineControllerTest extends WebTestCase
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

    public function testServiceReplacesProductsAndCoinsState(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['selector' => 'WATER', 'stock' => 2],
                    ['selector' => 'JUICE', 'stock' => 1],
                ],
                'coins' => [
                    '0.05' => 10,
                    '0.10' => 5,
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $products = array_map(
            static fn (array $product): array => [
                'selector' => $product['selector'],
                'price' => (float) $product['price'],
                'stock' => $product['stock'],
            ],
            $payload['products'],
        );

        self::assertSame(
            [
                ['selector' => 'JUICE', 'price' => 1.0, 'stock' => 1],
                ['selector' => 'SODA', 'price' => 1.5, 'stock' => 0],
                ['selector' => 'WATER', 'price' => 0.65, 'stock' => 2],
            ],
            $products,
        );

        self::assertSame(
            [
                '0.05' => 10,
                '0.10' => 5,
                '0.25' => 0,
                '1.00' => 0,
            ],
            $payload['machine_coins'],
        );

        self::assertSame(2, $this->productStock('WATER'));
        self::assertSame(1, $this->productStock('JUICE'));
        self::assertSame(0, $this->productStock('SODA'));
        self::assertSame(10, $this->coinCount(5));
        self::assertSame(5, $this->coinCount(10));
        self::assertSame(0, $this->coinCount(25));
        self::assertSame(0, $this->coinCount(100));
    }

    public function testServiceReturns400WhenSelectorIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['selector' => 'TEA', 'stock' => 2],
                ],
                'coins' => [
                    '0.05' => 1,
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    public function testServiceReturns400WhenCoinIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['selector' => 'WATER', 'stock' => 1],
                ],
                'coins' => [
                    '0.20' => 1,
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    public function testServiceReturns400WhenStockOrCoinCountIsNegative(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['selector' => 'WATER', 'stock' => -1],
                ],
                'coins' => [
                    '0.05' => 1,
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    private function productStock(string $selector): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT stock FROM machine_products WHERE selector = :selector',
            ['selector' => $selector],
        );
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
