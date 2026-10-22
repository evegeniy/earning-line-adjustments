<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Query;

use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistory;
use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistoryHandler;
use Payroll\Application\ReadModel\EarningLineHistory;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineHistoryStore;
use PHPUnit\Framework\TestCase;

final class GetEarningLineHistoryHandlerTest extends TestCase
{
    public function testReturnsTheProjectedHistory(): void
    {
        $histories = new InMemoryEarningLineHistoryStore();
        $history = new EarningLineHistory('line-42', Money::of('1000.00', Currency::USD));
        $histories->save($history);

        self::assertSame($history, (new GetEarningLineHistoryHandler($histories))(new GetEarningLineHistory('line-42')));
    }

    public function testFailsForUnknownLine(): void
    {
        $this->expectExceptionObject(EarningLineNotFound::withId(new EarningLineId('unknown')));

        (new GetEarningLineHistoryHandler(new InMemoryEarningLineHistoryStore()))(new GetEarningLineHistory('unknown'));
    }
}
