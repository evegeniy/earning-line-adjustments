<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Event;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\Shared\DomainEvent;
use Payroll\Domain\Shared\Money;

final readonly class EarningLineRecalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $previousValue,
        public Money $newValue,
        public DateTimeImmutable $calculatedAt,
    ) {}
}
