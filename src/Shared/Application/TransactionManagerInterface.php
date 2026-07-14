<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface TransactionManagerInterface
{
    public function run(callable $callback): mixed;
}
