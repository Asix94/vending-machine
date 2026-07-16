<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Application\TransactionManagerInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalTransactionManager implements TransactionManagerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function run(callable $callback): mixed
    {
        return $this->connection->transactional($callback);
    }
}
