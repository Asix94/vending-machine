<?php

declare(strict_types=1);

namespace App\Tests\Wallet\Domain\ValueObject;

use App\Wallet\Domain\ValueObject\WalletId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class WalletIdTest extends TestCase
{
    public function testCreateGeneratesValidUuidV4(): void
    {
        $walletId = WalletId::create();

        self::assertTrue(Uuid::isValid($walletId->value()));
        self::assertSame(4, Uuid::fromString($walletId->value())->getFields()->getVersion());
    }
}
