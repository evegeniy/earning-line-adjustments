<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Event;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\DomainEvent;
use Payroll\Domain\Shared\Money;

final readonly class ManualAdjustmentAdded implements DomainEvent
{
    /**
     * @param positive-int $sequence
     */
    public function __construct(
        public EarningLineId $lineId,
        public int $sequence,
        public Money $amount,
        public AdjustmentComment $comment,
        public SpecialistId $adjustedBy,
        public DateTimeImmutable $adjustedAt,
    ) {}
}
