<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Domain\EarningLine;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Payroll\Domain\EarningLine\Exception\InvalidAdjustment;
use Payroll\Domain\EarningLine\ManualAdjustment;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\CurrencyMismatch;
use Payroll\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

final class EarningLineTest extends TestCase
{
    private EarningLineId $lineId;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->lineId = new EarningLineId('line-42');
        $this->now = new DateTimeImmutable('2025-01-31 09:00:00 UTC');
    }

    public function testCalculatedLineIsWorthItsSystemValue(): void
    {
        $line = EarningLine::calculate($this->lineId, self::usd('1000.00'), $this->now);

        self::assertEquals(self::usd('1000.00'), $line->currentValue());
        self::assertFalse($line->isManuallyAdjusted());
        self::assertEquals(
            [new EarningLineCalculated($this->lineId, self::usd('1000.00'), $this->now)],
            $line->releaseEvents(),
        );
    }

    public function testFollowsSystemRecalculationsWhileNotManuallyAdjusted(): void
    {
        $line = $this->calculatedLine('1000.00');

        $line->recalculate(self::usd('1050.00'), $this->now);

        self::assertEquals(self::usd('1050.00'), $line->currentValue());
        self::assertEquals(
            [new EarningLineRecalculated($this->lineId, self::usd('1000.00'), self::usd('1050.00'), $this->now)],
            $line->releaseEvents(),
        );
    }

    public function testRecalculationToTheSameValueIsANoOp(): void
    {
        $line = $this->calculatedLine('1000.00');

        $line->recalculate(self::usd('1000.00'), $this->now);

        self::assertSame([], $line->releaseEvents());
    }

    public function testManualAdjustmentChangesTheCurrentValue(): void
    {
        $line = $this->calculatedLine('1050.00');

        $this->adjust($line, '-45.55', 'Employee declined dental benefit; reversing deduction');

        self::assertTrue($line->isManuallyAdjusted());
        self::assertEquals(self::usd('1050.00'), $line->systemValue());
        self::assertEquals(self::usd('1004.45'), $line->currentValue());
        self::assertEquals(
            [
                new ManualAdjustmentAdded(
                    $this->lineId,
                    1,
                    self::usd('-45.55'),
                    new AdjustmentComment('Employee declined dental benefit; reversing deduction'),
                    new SpecialistId('specialist-1'),
                    $this->now,
                ),
            ],
            $line->releaseEvents(),
        );
    }

    public function testManualAdjustmentRecordsWhoMadeItWhenAndWhy(): void
    {
        $line = $this->calculatedLine('1050.00');

        $this->adjust($line, '+100.10', 'Late correction: missed approved overtime bonus');

        self::assertEquals(
            [
                new ManualAdjustment(
                    1,
                    self::usd('100.10'),
                    new AdjustmentComment('Late correction: missed approved overtime bonus'),
                    new SpecialistId('specialist-1'),
                    $this->now,
                ),
            ],
            $line->adjustments(),
        );
    }

    public function testRecalculationIsIgnoredOnceTheLineIsManuallyAdjusted(): void
    {
        $line = $this->calculatedLine('1050.00');
        $this->adjust($line, '-45.55');
        $line->releaseEvents();

        $line->recalculate(self::usd('1200.00'), $this->now);

        self::assertEquals(self::usd('1050.00'), $line->systemValue());
        self::assertEquals(self::usd('1004.45'), $line->currentValue());
        self::assertEquals(
            [new SystemRecalculationIgnored($this->lineId, self::usd('1200.00'), $this->now)],
            $line->releaseEvents(),
        );
    }

    public function testMistakeIsFixedByACompensatingAdjustmentNotByEditingHistory(): void
    {
        $line = $this->calculatedLine('1104.45');

        $this->adjust($line, '-0.20', 'Second minor rounding adjustment');
        $this->adjust($line, '+0.20', 'Correcting mistake in adjustment #1');

        self::assertEquals(self::usd('1104.45'), $line->currentValue());
        self::assertSame(
            [[1, '-0.20'], [2, '0.20']],
            array_map(
                static fn(ManualAdjustment $adjustment): array => [$adjustment->sequence, $adjustment->amount->toDecimalString()],
                $line->adjustments(),
            ),
        );
    }

    public function testRejectsZeroAdjustment(): void
    {
        $line = $this->calculatedLine('1000.00');

        $this->expectExceptionObject(InvalidAdjustment::zeroAmount());

        $this->adjust($line, '0.00');
    }

    public function testRejectsAdjustmentInAnotherCurrency(): void
    {
        $line = $this->calculatedLine('1000.00');

        $this->expectExceptionObject(CurrencyMismatch::between(Currency::USD, Currency::EUR));

        $line->addManualAdjustment(
            Money::of('10.00', Currency::EUR),
            new AdjustmentComment('Wrong currency'),
            new SpecialistId('specialist-1'),
            $this->now,
        );
    }

    public function testRejectsRecalculationInAnotherCurrency(): void
    {
        $line = $this->calculatedLine('1000.00');

        $this->expectExceptionObject(CurrencyMismatch::between(Currency::USD, Currency::EUR));

        $line->recalculate(Money::of('1000.00', Currency::EUR), $this->now);
    }

    public function testRejectedAdjustmentLeavesTheLineUntouched(): void
    {
        $line = $this->calculatedLine('1000.00');

        try {
            $this->adjust($line, '0.00');
        } catch (InvalidAdjustment) {
        }

        self::assertFalse($line->isManuallyAdjusted());
        self::assertSame([], $line->releaseEvents());
    }

    private function calculatedLine(string $systemValue): EarningLine
    {
        $line = EarningLine::calculate($this->lineId, self::usd($systemValue), $this->now);
        $line->releaseEvents();

        return $line;
    }

    private function adjust(EarningLine $line, string $amount, string $comment = 'Some reason'): void
    {
        $line->addManualAdjustment(
            self::usd($amount),
            new AdjustmentComment($comment),
            new SpecialistId('specialist-1'),
            $this->now,
        );
    }

    private static function usd(string $amount): Money
    {
        return Money::of($amount, Currency::USD);
    }
}
