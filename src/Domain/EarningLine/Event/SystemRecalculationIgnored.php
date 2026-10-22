<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Event;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\Shared\DomainEvent;
use Payroll\Domain\Shared\Money;

/**
 * The system produced a new value for a line that has already been manually adjusted.
 * Nothing changed on the line, but the fact is worth knowing (e.g. to notify the specialist).
 */
final readonly class SystemRecalculationIgnored implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $ignoredValue,
        public DateTimeImmutable $calculatedAt,
    ) {}
}
