<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Command;

use Payroll\Application\Command\AddManualAdjustment\AddManualAdjustment;
use Payroll\Application\Command\AddManualAdjustment\AddManualAdjustmentHandler;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Payroll\Domain\EarningLine\Exception\InvalidAdjustment;
use Payroll\Domain\EarningLine\ManualAdjustment;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineRepository;
use Payroll\Tests\Support\TestClock;
use PHPUnit\Framework\TestCase;

final class AddManualAdjustmentHandlerTest extends TestCase
{
    private InMemoryEarningLineRepository $lines;

    private TestClock $clock;

    private AddManualAdjustmentHandler $handler;

    protected function setUp(): void
    {
        $this->lines = new InMemoryEarningLineRepository();
        $this->clock = new TestClock();
        $this->handler = new AddManualAdjustmentHandler($this->lines, $this->clock);

        $this->lines->save(EarningLine::calculate(new EarningLineId('line-42'), Money::of('1050.00', Currency::USD)));
    }

    public function testAddsAdjustmentStampedWithCurrentTime(): void
    {
        ($this->handler)(new AddManualAdjustment(
            lineId: 'line-42',
            amount: '-45.55',
            currency: 'USD',
            comment: 'Employee declined dental benefit; reversing deduction',
            specialistId: 'specialist-7',
        ));

        $line = $this->storedLine();
        self::assertEquals(Money::of('1004.45', Currency::USD), $line->currentValue());
        self::assertEquals(
            [
                new ManualAdjustment(
                    1,
                    Money::of('-45.55', Currency::USD),
                    new AdjustmentComment('Employee declined dental benefit; reversing deduction'),
                    new SpecialistId('specialist-7'),
                    $this->clock->now(),
                ),
            ],
            $line->adjustments(),
        );
    }

    public function testFailsForUnknownLine(): void
    {
        $this->expectExceptionObject(EarningLineNotFound::withId(new EarningLineId('unknown')));

        ($this->handler)(new AddManualAdjustment('unknown', '10.00', 'USD', 'Some reason', 'specialist-7'));
    }

    public function testInvalidAdjustmentIsNotSaved(): void
    {
        try {
            ($this->handler)(new AddManualAdjustment('line-42', '10.00', 'USD', '   ', 'specialist-7'));
            self::fail('An adjustment without a comment must be rejected.');
        } catch (InvalidAdjustment) {
        }

        self::assertFalse($this->storedLine()->isManuallyAdjusted());
    }

    private function storedLine(): EarningLine
    {
        $line = $this->lines->find(new EarningLineId('line-42'));
        self::assertNotNull($line);

        return $line;
    }
}
