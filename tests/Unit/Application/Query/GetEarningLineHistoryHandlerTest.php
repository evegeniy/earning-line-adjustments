<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Query;

use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistory;
use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistoryHandler;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineRepository;
use PHPUnit\Framework\TestCase;

final class GetEarningLineHistoryHandlerTest extends TestCase
{
    public function testReturnsTheStoredLine(): void
    {
        $lines = new InMemoryEarningLineRepository();
        $line = EarningLine::calculate(new EarningLineId('line-42'), Money::of('1000.00', Currency::USD));
        $lines->save($line);

        self::assertEquals($line, (new GetEarningLineHistoryHandler($lines))(new GetEarningLineHistory('line-42')));
    }

    public function testFailsForUnknownLine(): void
    {
        $this->expectExceptionObject(EarningLineNotFound::withId(new EarningLineId('unknown')));

        (new GetEarningLineHistoryHandler(new InMemoryEarningLineRepository()))(new GetEarningLineHistory('unknown'));
    }
}
