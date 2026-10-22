<?php

declare(strict_types=1);

namespace Payroll\Application\Command\ApplySystemCalculation;

use Payroll\Application\EventBus;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\EarningLineRepository;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Psr\Clock\ClockInterface;

final readonly class ApplySystemCalculationHandler
{
    public function __construct(
        private EarningLineRepository $lines,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ApplySystemCalculation $command): void
    {
        $id = new EarningLineId($command->lineId);
        $systemValue = Money::of($command->amount, Currency::from($command->currency));

        $line = $this->lines->find($id);

        if ($line === null) {
            $line = EarningLine::calculate($id, $systemValue, $this->clock->now());
        } else {
            $line->recalculate($systemValue, $this->clock->now());
        }

        $this->lines->save($line);
        $this->eventBus->publish(...$line->releaseEvents());
    }
}
