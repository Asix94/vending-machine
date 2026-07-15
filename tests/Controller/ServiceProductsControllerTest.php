<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ServiceProductsControllerTest extends WebTestCase
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

    public function testServiceProductsAddsStockToExistingValues(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/products',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['product' => 'WATER', 'quantity_to_add' => 2],
                    ['product' => 'WATER', 'quantity_to_add' => 1],
                    ['product' => 'JUICE', 'quantity_to_add' => 3],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('products', $payload);
        self::assertArrayNotHasKey('machine_coins', $payload);
        self::assertSame(3, $this->productStock('WATER'));
        self::assertSame(3, $this->productStock('JUICE'));
        self::assertSame(0, $this->productStock('SODA'));
    }

    public function testServiceProductsReturns400WhenSelectorIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/products',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['product' => 'TEA', 'quantity_to_add' => 1],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_payload', $this->errorCode());
    }

    public function testServiceProductsReturns400WhenQuantityToAddIsNotPositive(): void
    {
        $this->client->request(
            'POST',
            '/vending-machine/service/products',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'products' => [
                    ['product' => 'WATER', 'quantity_to_add' => 0],
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

    private function errorCode(): string
    {
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (string) $payload['error'];
    }
}
