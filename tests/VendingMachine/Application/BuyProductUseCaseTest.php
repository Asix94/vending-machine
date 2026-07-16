<?php

declare(strict_types=1);

namespace App\Tests\VendingMachine\Application;

use App\Shared\Application\TransactionManagerInterface;
use App\VendingMachine\Application\BuyProductUseCase;
use App\VendingMachine\Application\Dto\BuyProductRequest;
use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Exception\InsufficientFundsException;
use App\VendingMachine\Domain\Exception\OutOfStockException;
use App\VendingMachine\Domain\Repository\VendingMachineRepositoryInterface;
use App\VendingMachine\Domain\Service\ExactChangeCalculator;
use App\VendingMachine\Domain\ValueObject\Product;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class BuyProductUseCaseTest extends TestCase
{
    public function testItBuysProductReturnsChangeAndResetsWallet(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(100), [100 => 1]);

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($wallet);

        $walletRepository
            ->expects(self::once())
            ->method('update')
            ->with(self::callback(static fn (Wallet $updatedWallet): bool => $updatedWallet->balance()->cents() === 0));

        $machineRepository = $this->createMock(VendingMachineRepositoryInterface::class);
        $machineRepository
            ->expects(self::once())
            ->method('findProductBySelector')
            ->with('WATER')
            ->willReturn(new Product('WATER', 65, 2));

        $machineRepository
            ->expects(self::once())
            ->method('getMachineCoins')
            ->willReturn([
                5 => 0,
                10 => 1,
                25 => 1,
                100 => 0,
            ]);

        $machineRepository
            ->expects(self::once())
            ->method('updateMachineState')
            ->with(
                'WATER',
                1,
                [
                    5 => 0,
                    10 => 0,
                    25 => 0,
                    100 => 1,
                ],
            );

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $useCase = new BuyProductUseCase($walletRepository, $machineRepository, $transactionManager, new ExactChangeCalculator());

        $response = $useCase(new BuyProductRequest(
            '8cf752a6-6e5f-4b88-a531-d0e57dda61b3',
            $walletId,
            'water',
        ));

        self::assertSame('WATER', $response->selector);
        self::assertSame(0.65, $response->price);
        self::assertSame([0.25, 0.1], $response->change);
        self::assertSame(0.0, $response->walletBalanceAfter);
    }

    public function testItThrowsWhenInsufficientFunds(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(25), [25 => 1]);

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($wallet);
        $walletRepository->expects(self::never())->method('update');

        $machineRepository = $this->createMock(VendingMachineRepositoryInterface::class);
        $machineRepository
            ->expects(self::once())
            ->method('findProductBySelector')
            ->with('SODA')
            ->willReturn(new Product('SODA', 150, 1));
        $machineRepository->expects(self::never())->method('getMachineCoins');
        $machineRepository->expects(self::never())->method('updateMachineState');

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $useCase = new BuyProductUseCase($walletRepository, $machineRepository, $transactionManager, new ExactChangeCalculator());

        $this->expectException(InsufficientFundsException::class);
        $useCase(new BuyProductRequest(
            '8cf752a6-6e5f-4b88-a531-d0e57dda61b3',
            $walletId,
            'soda',
        ));
    }

    public function testItThrowsWhenOutOfStock(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(100), [100 => 1]);

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($wallet);
        $walletRepository->expects(self::never())->method('update');

        $machineRepository = $this->createMock(VendingMachineRepositoryInterface::class);
        $machineRepository
            ->expects(self::once())
            ->method('findProductBySelector')
            ->with('WATER')
            ->willReturn(new Product('WATER', 65, 0));
        $machineRepository->expects(self::never())->method('getMachineCoins');
        $machineRepository->expects(self::never())->method('updateMachineState');

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $useCase = new BuyProductUseCase($walletRepository, $machineRepository, $transactionManager, new ExactChangeCalculator());

        $this->expectException(OutOfStockException::class);
        $useCase(new BuyProductRequest(
            '8cf752a6-6e5f-4b88-a531-d0e57dda61b3',
            $walletId,
            'water',
        ));
    }

    public function testItThrowsWhenExactChangeCannotBeMade(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(100), [100 => 1]);

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($wallet);
        $walletRepository->expects(self::never())->method('update');

        $machineRepository = $this->createMock(VendingMachineRepositoryInterface::class);
        $machineRepository
            ->expects(self::once())
            ->method('findProductBySelector')
            ->with('WATER')
            ->willReturn(new Product('WATER', 65, 1));

        $machineRepository
            ->expects(self::once())
            ->method('getMachineCoins')
            ->willReturn([
                5 => 0,
                10 => 0,
                25 => 0,
                100 => 0,
            ]);

        $machineRepository->expects(self::never())->method('updateMachineState');

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $useCase = new BuyProductUseCase($walletRepository, $machineRepository, $transactionManager, new ExactChangeCalculator());

        $this->expectException(CannotMakeExactChangeException::class);
        $useCase(new BuyProductRequest(
            '8cf752a6-6e5f-4b88-a531-d0e57dda61b3',
            $walletId,
            'water',
        ));
    }

    public function testItBuysProductWhenGreedyWouldFailButExactChangeExists(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(100), [100 => 1]);

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($wallet);

        $walletRepository
            ->expects(self::once())
            ->method('update')
            ->with(self::callback(static fn (Wallet $updatedWallet): bool => $updatedWallet->balance()->cents() === 0));

        $machineRepository = $this->createMock(VendingMachineRepositoryInterface::class);
        $machineRepository
            ->expects(self::once())
            ->method('findProductBySelector')
            ->with('WATER')
            ->willReturn(new Product('WATER', 70, 2));

        $machineRepository
            ->expects(self::once())
            ->method('getMachineCoins')
            ->willReturn([
                5 => 0,
                10 => 3,
                25 => 1,
                100 => 0,
            ]);

        $machineRepository
            ->expects(self::once())
            ->method('updateMachineState')
            ->with(
                'WATER',
                1,
                [
                    5 => 0,
                    10 => 0,
                    25 => 1,
                    100 => 1,
                ],
            );

        $transactionManager = $this->createMock(TransactionManagerInterface::class);
        $transactionManager
            ->expects(self::once())
            ->method('run')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $useCase = new BuyProductUseCase($walletRepository, $machineRepository, $transactionManager, new ExactChangeCalculator());

        $response = $useCase(new BuyProductRequest(
            '8cf752a6-6e5f-4b88-a531-d0e57dda61b3',
            $walletId,
            'water',
        ));

        self::assertSame('WATER', $response->selector);
        self::assertSame(0.7, $response->price);
        self::assertSame([0.1, 0.1, 0.1], $response->change);
        self::assertSame(0.0, $response->walletBalanceAfter);
    }
}
