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
        $this->connection->beginTransaction();

        try {
            $result = $callback();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
}
