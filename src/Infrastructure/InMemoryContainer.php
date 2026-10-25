<?php

declare(strict_types=1);

namespace Payroll\Infrastructure;

use Payroll\Application\Command\AddManualAdjustment\AddManualAdjustmentHandler;
use Payroll\Application\Command\ApplySystemCalculation\ApplySystemCalculationHandler;
use Payroll\Application\Query\GetEarningLineHistory\GetEarningLineHistoryHandler;
use Payroll\Infrastructure\Clock\SystemClock;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineRepository;
use Psr\Clock\ClockInterface;

/**
 * Composition root: the only place that knows which adapter implements which port.
 * In a framework this wiring would live in the DI container configuration instead.
 */
final readonly class InMemoryContainer
{
    public ApplySystemCalculationHandler $applySystemCalculation;

    public AddManualAdjustmentHandler $addManualAdjustment;

    public GetEarningLineHistoryHandler $getEarningLineHistory;

    public function __construct(ClockInterface $clock = new SystemClock())
    {
        $lines = new InMemoryEarningLineRepository();

        $this->applySystemCalculation = new ApplySystemCalculationHandler($lines);
        $this->addManualAdjustment = new AddManualAdjustmentHandler($lines, $clock);
        $this->getEarningLineHistory = new GetEarningLineHistoryHandler($lines);
    }
}
