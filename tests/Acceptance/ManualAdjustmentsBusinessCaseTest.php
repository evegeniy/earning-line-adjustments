<?php

declare(strict_types=1);

namespace Payroll\Tests\Acceptance;

use Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use Payroll\Application\Command\ApplySystemCalculation\ApplySystemCalculation;
use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistory;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\ManualAdjustment;
use Payroll\Infrastructure\InMemoryContainer;
use Payroll\Tests\Support\TestClock;
use PHPUnit\Framework\TestCase;

/**
 * Replays the business case from the assignment step by step through the application's
 * public API (commands in, query out) and checks every number the assignment expects.
 */
final class ManualAdjustmentsBusinessCaseTest extends TestCase
{
    private const string LINE_ID = 'base-salary-2025-01';

    private TestClock $clock;

    private InMemoryContainer $app;

    protected function setUp(): void
    {
        $this->clock = new TestClock();
        $this->app = new InMemoryContainer($this->clock);
    }

    public function testCurrentValueAfterEveryStep(): void
    {
        $this->systemCalculates('1000.00');
        $this->assertCurrentValue('1000.00', 'Step 1: system calculates the line');

        $this->systemCalculates('1050.00');
        $this->assertCurrentValue('1050.00', 'Step 2: no manual correction yet, recalculation is applied');

        $this->specialistAdjusts('-45.55', 'Employee declined dental benefit; reversing deduction');
        $this->assertCurrentValue('1004.45', 'Step 3: first manual correction');

        $this->systemCalculates('1100.00');
        $this->assertCurrentValue('1004.45', 'Step 4: recalculation must be ignored');

        $this->specialistAdjusts('+100.10', 'Late correction: missed approved overtime bonus');
        $this->assertCurrentValue('1104.55', 'Step 5: second correction');

        $this->specialistAdjusts('-0.10', 'Minor rounding adjustment');
        $this->assertCurrentValue('1104.45', 'Step 6: third correction');

        $this->specialistAdjusts('-0.20', 'Second minor rounding adjustment');
        $this->assertCurrentValue('1104.25', 'Step 7: fourth correction');

        $this->specialistAdjusts('+0.20', 'Correcting mistake in adjustment #4');
        $this->assertCurrentValue('1104.45', 'Step 8: compensating correction');
    }

    public function testFinalAuditHistory(): void
    {
        $this->replayBusinessCase();

        $line = $this->history();

        self::assertTrue($line->isManuallyAdjusted());
        self::assertSame('1050.00', $line->systemValue()->toDecimalString(), 'System value frozen at step 3');
        self::assertSame(
            [
                [1, '-45.55', 'Employee declined dental benefit; reversing deduction'],
                [2, '100.10', 'Late correction: missed approved overtime bonus'],
                [3, '-0.10', 'Minor rounding adjustment'],
                [4, '-0.20', 'Second minor rounding adjustment'],
                [5, '0.20', 'Correcting mistake in adjustment #4'],
            ],
            array_map(
                static fn(ManualAdjustment $adjustment): array => [$adjustment->sequence, $adjustment->amount->toDecimalString(), $adjustment->comment->value],
                $line->adjustments(),
            ),
        );
        self::assertSame('1104.45', $line->currentValue()->toDecimalString());
    }

    public function testEveryAdjustmentIsTraceableToItsAuthorAndTime(): void
    {
        $this->systemCalculates('1050.00');
        $this->specialistAdjusts('-45.55', 'Employee declined dental benefit; reversing deduction');
        $adjustedAt = $this->clock->now();

        $adjustment = $this->history()->adjustments()[0];

        self::assertSame('specialist-1', $adjustment->adjustedBy->value);
        self::assertEquals($adjustedAt, $adjustment->adjustedAt);
    }

    private function replayBusinessCase(): void
    {
        $this->systemCalculates('1000.00');
        $this->systemCalculates('1050.00');
        $this->specialistAdjusts('-45.55', 'Employee declined dental benefit; reversing deduction');
        $this->systemCalculates('1100.00');
        $this->specialistAdjusts('+100.10', 'Late correction: missed approved overtime bonus');
        $this->specialistAdjusts('-0.10', 'Minor rounding adjustment');
        $this->specialistAdjusts('-0.20', 'Second minor rounding adjustment');
        $this->specialistAdjusts('+0.20', 'Correcting mistake in adjustment #4');
    }

    private function systemCalculates(string $amount): void
    {
        ($this->app->applySystemCalculation)(new ApplySystemCalculation(self::LINE_ID, $amount, 'USD'));
    }

    private function specialistAdjusts(string $amount, string $comment): void
    {
        $this->clock->advance('PT1H');
        ($this->app->addManualAdjustment)(new AddManualAdjustment(self::LINE_ID, $amount, 'USD', $comment, 'specialist-1'));
    }

    private function assertCurrentValue(string $expected, string $step): void
    {
        self::assertSame($expected, $this->history()->currentValue()->toDecimalString(), $step);
    }

    private function history(): EarningLine
    {
        return ($this->app->getEarningLineHistory)(new GetEarningLineHistory(self::LINE_ID));
    }
}
