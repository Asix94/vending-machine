<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Application;

use App\Wallet\Application\AddMoneyUseCase;
use App\Wallet\Application\Dto\AddMoneyRequest;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class AddMoneyUseCaseTest extends TestCase
{
    public function testItAddsMoneyAndPersistsWalletState(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(new WalletId($walletId), new Balance(0));

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (WalletId $id): bool => $id->value() === $walletId))
            ->willReturn($wallet);

        $walletRepository
            ->expects(self::once())
            ->method('update')
            ->with(self::callback(static fn (Wallet $updatedWallet): bool => $updatedWallet->balance()->cents() === 135));

        $useCase = new AddMoneyUseCase($walletRepository);

        $response = $useCase(new AddMoneyRequest($walletId, [0.25, 1.0, 0.1]));

        self::assertSame($walletId, $response->walletId);
        self::assertSame(1.35, $response->insertedBalance);
        self::assertSame(
            [
                '0.05' => 0,
                '0.10' => 1,
                '0.25' => 1,
                '1.00' => 1,
            ],
            $response->insertedCoins,
        );
    }
}
