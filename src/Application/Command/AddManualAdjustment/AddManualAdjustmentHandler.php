<?php

declare(strict_types=1);

namespace Payroll\Application\Command\AddManualAdjustment;

use Payroll\Application\EventBus;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\EarningLineRepository;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Psr\Clock\ClockInterface;

final readonly class AddManualAdjustmentHandler
{
    public function __construct(
        private EarningLineRepository $lines,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AddManualAdjustment $command): void
    {
        $id = new EarningLineId($command->lineId);
        $line = $this->lines->find($id) ?? throw EarningLineNotFound::withId($id);

        $line->addManualAdjustment(
            Money::of($command->amount, Currency::from($command->currency)),
            new AdjustmentComment($command->comment),
            new SpecialistId($command->specialistId),
            $this->clock->now(),
        );

        $this->lines->save($line);
        $this->eventBus->publish(...$line->releaseEvents());
    }
}
