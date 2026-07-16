<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Application;

use App\Wallet\Application\Dto\ReturnCoinRequest;
use App\Wallet\Application\ReturnCoinUseCase;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Balance;
use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;

final class ReturnCoinUseCaseTest extends TestCase
{
    public function testItReturnsAllCoinsAndResetsWallet(): void
    {
        $walletId = 'fc599d0c-dc16-4c7b-bc39-ef67b8edbfd7';
        $wallet = new Wallet(
            new WalletId($walletId),
            new Balance(135),
            [
                10 => 1,
                25 => 1,
                100 => 1,
            ],
        );

        $walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (WalletId $id): bool => $id->value() === $walletId))
            ->willReturn($wallet);

        $walletRepository
            ->expects(self::once())
            ->method('update')
            ->with(self::callback(static fn (Wallet $updatedWallet): bool => $updatedWallet->balance()->cents() === 0));

        $useCase = new ReturnCoinUseCase($walletRepository);

        $response = $useCase(new ReturnCoinRequest($walletId));

        self::assertSame($walletId, $response->walletId);
        self::assertSame([1, 0.25, 0.1], $response->returnedCoins);
        self::assertSame(1.35, $response->returnedTotal);
        self::assertSame(0.0, $response->walletBalanceAfter);
    }
}
