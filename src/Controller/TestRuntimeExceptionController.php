<?php

declare(strict_types=1);

namespace App\Controller;

use RuntimeException;

final class TestRuntimeExceptionController
{
    public function __invoke(): never
    {
        throw new RuntimeException('Intentional test exception.');
    }
}
