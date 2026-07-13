<?php

declare(strict_types=1);

namespace App\Wallet\Domain\Exception;

use DomainException;

final class WalletNotFoundException extends DomainException
{
    public function __construct(string $walletId)
    {
        parent::__construct(sprintf('Wallet with id "%s" was not found.', $walletId));
    }
}
