<?php

declare(strict_types=1);

namespace App\Tests\VendingMachine\Application;

use App\VendingMachine\Application\Dto\ServiceProductsRequest;
use App\VendingMachine\Application\ServiceProductsUseCase;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceProductsUseCaseTest extends TestCase
{
    public function testItAggregatesAndPersistsProductStockIncrements(): void
    {
        $repository = $this->createMock(VendingMachineRepositoryInterface::class);

        $repository
            ->expects(self::exactly(2))
            ->method('getAllProducts')
            ->willReturnOnConsecutiveCalls(
                [
                    ['selector' => 'WATER', 'price_cents' => 65, 'stock' => 2],
                    ['selector' => 'JUICE', 'price_cents' => 100, 'stock' => 1],
                    ['selector' => 'SODA', 'price_cents' => 150, 'stock' => 0],
                ],
                [
                    ['selector' => 'WATER', 'price_cents' => 65, 'stock' => 5],
                    ['selector' => 'JUICE', 'price_cents' => 100, 'stock' => 3],
                    ['selector' => 'SODA', 'price_cents' => 150, 'stock' => 0],
                ],
            );

        $repository
            ->expects(self::once())
            ->method('incrementProductStocks')
            ->with(['WATER' => 3, 'JUICE' => 2]);

        $useCase = new ServiceProductsUseCase($repository);

        $response = $useCase(new ServiceProductsRequest([
            ['product' => 'WATER', 'quantity_to_add' => 2],
            ['product' => 'water', 'quantity_to_add' => 1],
            ['product' => 'JUICE', 'quantity_to_add' => 2],
        ]));

        self::assertSame(
            [
                ['selector' => 'WATER', 'price' => 0.65, 'stock' => 5],
                ['selector' => 'JUICE', 'price' => 1, 'stock' => 3],
                ['selector' => 'SODA', 'price' => 1.5, 'stock' => 0],
            ],
            $response->products,
        );
        self::assertSame([], $response->machineCoins);
    }

    public function testItRejectsInvalidProductSelector(): void
    {
        $repository = $this->createMock(VendingMachineRepositoryInterface::class);

        $repository
            ->expects(self::once())
            ->method('getAllProducts')
            ->willReturn([
                ['selector' => 'WATER', 'price_cents' => 65, 'stock' => 2],
                ['selector' => 'JUICE', 'price_cents' => 100, 'stock' => 1],
                ['selector' => 'SODA', 'price_cents' => 150, 'stock' => 0],
            ]);

        $repository
            ->expects(self::never())
            ->method('incrementProductStocks');

        $useCase = new ServiceProductsUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $useCase(new ServiceProductsRequest([
            ['product' => 'TEA', 'quantity_to_add' => 1],
        ]));
    }
}
