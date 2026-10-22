<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Event;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\Shared\DomainEvent;
use Payroll\Domain\Shared\Money;

final readonly class EarningLineCalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $systemValue,
        public DateTimeImmutable $calculatedAt,
    ) {}
}
