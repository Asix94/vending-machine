<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Application;

use App\Wallet\Application\CreateWalletUseCase;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateWalletUseCaseTest extends TestCase
{
    public function testItCreatesAndPersistsWalletWithZeroBalance(): void
    {
        $walletRepository = $this->createMock(WalletRepositoryInterface::class);

        $walletRepository
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(function (Wallet $wallet): bool {
                self::assertTrue(Uuid::isValid($wallet->walletId()->value()));
                self::assertSame(0, $wallet->balance()->cents());

                return true;
            }));

        $useCase = new CreateWalletUseCase($walletRepository);

        $response = $useCase();

        self::assertTrue(Uuid::isValid($response->walletId));
        self::assertSame(0.0, $response->insertedBalance);
    }
}
