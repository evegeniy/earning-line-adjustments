<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

use LogicException;
use Payroll\Application\EventSubscriber;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Payroll\Domain\Shared\DomainEvent;

/**
 * Keeps the EarningLineHistory read model in sync with the write side by projecting its domain events.
 */
final readonly class EarningLineHistoryProjector implements EventSubscriber
{
    public function __construct(
        private EarningLineHistoryStore $histories,
    ) {}

    public function __invoke(DomainEvent $event): void
    {
        if ($event instanceof EarningLineCalculated) {
            $this->histories->save(new EarningLineHistory($event->lineId->value, $event->systemValue));
        } elseif ($event instanceof EarningLineRecalculated) {
            $this->histories->save($this->historyOf($event->lineId)->withSystemValue($event->newValue));
        } elseif ($event instanceof ManualAdjustmentAdded) {
            $this->histories->save($this->historyOf($event->lineId)->withAdjustment(new AdjustmentEntry(
                $event->sequence,
                $event->amount,
                $event->comment->value,
                $event->adjustedBy->value,
                $event->adjustedAt,
            )));
        }
        // SystemRecalculationIgnored is not projected: by definition it changed nothing on the line.
    }

    private function historyOf(EarningLineId $lineId): EarningLineHistory
    {
        return $this->histories->find($lineId->value)
            ?? throw new LogicException(sprintf('No history has been projected for earning line "%s".', $lineId->value));
    }
}
