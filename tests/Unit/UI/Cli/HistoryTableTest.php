<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\UI\Cli;

use DateTimeImmutable;
use Payroll\Application\ReadModel\AdjustmentEntry;
use Payroll\Application\ReadModel\EarningLineHistory;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\UI\Cli\HistoryTable;
use PHPUnit\Framework\TestCase;

final class HistoryTableTest extends TestCase
{
    public function testRendersFrozenSystemValueAdjustmentsAndCurrentValue(): void
    {
        $history = new EarningLineHistory('line-42', self::usd('1050.00'), [
            new AdjustmentEntry(1, self::usd('-45.55'), 'Employee declined dental benefit', 'specialist-1', new DateTimeImmutable('2025-01-31 12:00')),
            new AdjustmentEntry(2, self::usd('100.10'), 'Missed overtime bonus', 'specialist-2', new DateTimeImmutable('2025-01-31 14:30')),
        ]);

        self::assertSame(
            <<<'TABLE'
                Entry                       Value   Comment                            By             At (UTC)
                ---------------------   ---------   --------------------------------   ------------   ----------------
                System value (frozen)   $1,050.00
                Adjustment 1              -$45.55   Employee declined dental benefit   specialist-1   2025-01-31 12:00
                Adjustment 2             +$100.10   Missed overtime bonus              specialist-2   2025-01-31 14:30
                Current value           $1,104.55

                TABLE,
            HistoryTable::render($history),
        );
    }

    public function testDoesNotCallSystemValueFrozenBeforeAnyAdjustment(): void
    {
        $output = HistoryTable::render(new EarningLineHistory('line-42', self::usd('1000.00')));

        self::assertMatchesRegularExpression('/^System value +\$1,000\.00$/m', $output);
        self::assertStringNotContainsString('frozen', $output);
    }

    private static function usd(string $amount): Money
    {
        return Money::of($amount, Currency::USD);
    }
}
