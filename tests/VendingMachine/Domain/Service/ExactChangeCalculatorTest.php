<?php

declare(strict_types=1);

namespace App\Tests\VendingMachine\Domain\Service;

use App\VendingMachine\Domain\Exception\CannotMakeExactChangeException;
use App\VendingMachine\Domain\Service\ExactChangeCalculator;
use PHPUnit\Framework\TestCase;

final class ExactChangeCalculatorTest extends TestCase
{
    public function testItFindsSolutionWhenGreedyWouldFail(): void
    {
        $calculator = new ExactChangeCalculator();

        $result = $calculator->calculate(30, [
            5 => 0,
            10 => 3,
            25 => 1,
            100 => 0,
        ]);

        self::assertSame([
            5 => 0,
            10 => 3,
            25 => 0,
            100 => 0,
        ], $result);
    }

    public function testItThrowsWhenNoExactChangeIsPossible(): void
    {
        $calculator = new ExactChangeCalculator();

        $this->expectException(CannotMakeExactChangeException::class);
        $calculator->calculate(30, [
            5 => 0,
            10 => 0,
            25 => 1,
            100 => 0,
        ]);
    }

    public function testItReturnsZeroCoinsForZeroChange(): void
    {
        $calculator = new ExactChangeCalculator();

        $result = $calculator->calculate(0, [
            5 => 2,
            10 => 1,
            25 => 1,
            100 => 0,
        ]);

        self::assertSame([
            5 => 0,
            10 => 0,
            25 => 0,
            100 => 0,
        ], $result);
    }

    public function testItRespectsAvailableCoinInventory(): void
    {
        $calculator = new ExactChangeCalculator();

        $result = $calculator->calculate(40, [
            5 => 2,
            10 => 1,
            25 => 1,
            100 => 0,
        ]);

        self::assertSame([
            5 => 1,
            10 => 1,
            25 => 1,
            100 => 0,
        ], $result);
    }
}
