<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Command;

use DateTimeImmutable;
use Payroll\Application\Command\ApplySystemCalculation\ApplySystemCalculation;
use Payroll\Application\Command\ApplySystemCalculation\ApplySystemCalculationHandler;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineRepository;
use PHPUnit\Framework\TestCase;

final class ApplySystemCalculationHandlerTest extends TestCase
{
    private InMemoryEarningLineRepository $lines;

    private ApplySystemCalculationHandler $handler;

    protected function setUp(): void
    {
        $this->lines = new InMemoryEarningLineRepository();
        $this->handler = new ApplySystemCalculationHandler($this->lines);
    }

    public function testFirstCalculationCreatesTheLine(): void
    {
        ($this->handler)(new ApplySystemCalculation('line-42', '1000.00', 'USD'));

        self::assertEquals(self::usd('1000.00'), $this->storedLine()->currentValue());
    }

    public function testNextCalculationRecalculatesTheExistingLine(): void
    {
        ($this->handler)(new ApplySystemCalculation('line-42', '1000.00', 'USD'));

        ($this->handler)(new ApplySystemCalculation('line-42', '1050.00', 'USD'));

        self::assertEquals(self::usd('1050.00'), $this->storedLine()->currentValue());
    }

    public function testCalculationOfManuallyAdjustedLineIsIgnored(): void
    {
        $line = EarningLine::calculate(new EarningLineId('line-42'), self::usd('1050.00'));
        $line->addManualAdjustment(
            self::usd('-45.55'),
            new AdjustmentComment('Employee declined dental benefit'),
            new SpecialistId('specialist-1'),
            new DateTimeImmutable(),
        );
        $this->lines->save($line);

        ($this->handler)(new ApplySystemCalculation('line-42', '1200.00', 'USD'));

        self::assertEquals(self::usd('1004.45'), $this->storedLine()->currentValue());
    }

    private function storedLine(): EarningLine
    {
        $line = $this->lines->find(new EarningLineId('line-42'));
        self::assertNotNull($line);

        return $line;
    }

    private static function usd(string $amount): Money
    {
        return Money::of($amount, Currency::USD);
    }
}
