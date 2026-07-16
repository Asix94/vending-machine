<?php

declare(strict_types=1);

namespace App\Tests\VendingMachine\Application;

use App\VendingMachine\Application\Dto\ServiceCoinsRequest;
use App\VendingMachine\Application\ServiceCoinsUseCase;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceCoinsUseCaseTest extends TestCase
{
    public function testItAggregatesAndPersistsCoinIncrements(): void
    {
        $repository = $this->createMock(VendingMachineRepositoryInterface::class);

        $repository
            ->expects(self::once())
            ->method('incrementMachineCoins')
            ->with([
                25 => 3,
                100 => 2,
            ]);

        $repository
            ->expects(self::once())
            ->method('getMachineCoins')
            ->willReturn([
                5 => 0,
                10 => 0,
                25 => 3,
                100 => 2,
            ]);

        $useCase = new ServiceCoinsUseCase($repository);

        $response = $useCase(new ServiceCoinsRequest([
            ['coin' => '0.25', 'quantity_to_add' => 2],
            ['coin' => '0.25', 'quantity_to_add' => 1],
            ['coin' => '1.00', 'quantity_to_add' => 2],
        ]));

        self::assertSame([], $response->products);
        self::assertSame(
            [
                '0.05' => 0,
                '0.10' => 0,
                '0.25' => 3,
                '1.00' => 2,
            ],
            $response->machineCoins,
        );
    }

    public function testItRejectsInvalidCoinDenomination(): void
    {
        $repository = $this->createMock(VendingMachineRepositoryInterface::class);

        $repository
            ->expects(self::never())
            ->method('incrementMachineCoins');

        $useCase = new ServiceCoinsUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $useCase(new ServiceCoinsRequest([
            ['coin' => '0.20', 'quantity_to_add' => 1],
        ]));
    }

    public function testItRejectsRoundedEdgeCoinValues(): void
    {
        $repository = $this->createMock(VendingMachineRepositoryInterface::class);

        $repository
            ->expects(self::never())
            ->method('incrementMachineCoins');

        $useCase = new ServiceCoinsUseCase($repository);

        foreach (['0.049', '0.051', '0.099', '1.001'] as $coin) {
            try {
                $useCase(new ServiceCoinsRequest([
                    ['coin' => $coin, 'quantity_to_add' => 1],
                ]));
                self::fail('Expected InvalidArgumentException for invalid coin: '.$coin);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
