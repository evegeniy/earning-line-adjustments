<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\ReadModel;

use DateTimeImmutable;
use Payroll\Application\ReadModel\AdjustmentEntry;
use Payroll\Application\ReadModel\EarningLineHistory;
use Payroll\Application\ReadModel\EarningLineHistoryProjector;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineHistoryStore;
use PHPUnit\Framework\TestCase;

final class EarningLineHistoryProjectorTest extends TestCase
{
    private InMemoryEarningLineHistoryStore $histories;

    private EarningLineHistoryProjector $projector;

    private EarningLineId $lineId;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->histories = new InMemoryEarningLineHistoryStore();
        $this->projector = new EarningLineHistoryProjector($this->histories);
        $this->lineId = new EarningLineId('line-42');
        $this->now = new DateTimeImmutable('2025-01-31 09:00:00 UTC');

        ($this->projector)(new EarningLineCalculated($this->lineId, self::usd('1000.00'), $this->now));
    }

    public function testStartsTheHistoryWhenTheLineIsCalculated(): void
    {
        self::assertEquals(new EarningLineHistory('line-42', self::usd('1000.00')), $this->history());
        self::assertFalse($this->history()->isSystemValueFrozen());
    }

    public function testFollowsRecalculations(): void
    {
        ($this->projector)(new EarningLineRecalculated($this->lineId, self::usd('1000.00'), self::usd('1050.00'), $this->now));

        self::assertEquals(self::usd('1050.00'), $this->history()->systemValue);
        self::assertEquals(self::usd('1050.00'), $this->history()->currentValue);
    }

    public function testAppendsAdjustmentsAndDerivesTheCurrentValue(): void
    {
        ($this->projector)($this->adjustmentAdded(1, '-45.55'));
        ($this->projector)($this->adjustmentAdded(2, '+100.10'));

        $history = $this->history();
        self::assertTrue($history->isSystemValueFrozen());
        self::assertEquals(self::usd('1000.00'), $history->systemValue);
        self::assertEquals(self::usd('1054.55'), $history->currentValue);
        self::assertEquals(
            new AdjustmentEntry(1, self::usd('-45.55'), 'Some reason', 'specialist-1', $this->now),
            $history->adjustments[0],
        );
    }

    public function testIgnoredRecalculationLeavesTheHistoryUntouched(): void
    {
        ($this->projector)($this->adjustmentAdded(1, '-45.55'));
        $before = $this->history();

        ($this->projector)(new SystemRecalculationIgnored($this->lineId, self::usd('1200.00'), $this->now));

        self::assertSame($before, $this->history());
    }

    public function testRedeliveredAdjustmentIsProjectedOnlyOnce(): void
    {
        ($this->projector)($this->adjustmentAdded(1, '-45.55'));
        ($this->projector)($this->adjustmentAdded(1, '-45.55'));

        self::assertCount(1, $this->history()->adjustments);
        self::assertEquals(self::usd('954.45'), $this->history()->currentValue);
    }

    /**
     * @param positive-int $sequence
     */
    private function adjustmentAdded(int $sequence, string $amount): ManualAdjustmentAdded
    {
        return new ManualAdjustmentAdded(
            $this->lineId,
            $sequence,
            self::usd($amount),
            new AdjustmentComment('Some reason'),
            new SpecialistId('specialist-1'),
            $this->now,
        );
    }

    private function history(): EarningLineHistory
    {
        $history = $this->histories->find('line-42');
        self::assertNotNull($history);

        return $history;
    }

    private static function usd(string $amount): Money
    {
        return Money::of($amount, Currency::USD);
    }
}
