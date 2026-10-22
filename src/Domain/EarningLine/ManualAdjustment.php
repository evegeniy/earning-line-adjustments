<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\Exception\InvalidAdjustment;
use Payroll\Domain\Shared\Money;

/**
 * One manual correction of an earning line.
 *
 * Immutable by design: there is no way to edit or remove an adjustment once it is part of
 * the line. A mistake is fixed by adding another, compensating adjustment.
 */
final readonly class ManualAdjustment
{
    /**
     * @param positive-int $sequence 1-based position in the line's history, i.e. "adjustment #4"
     */
    public function __construct(
        public int $sequence,
        public Money $amount,
        public AdjustmentComment $comment,
        public SpecialistId $adjustedBy,
        public DateTimeImmutable $adjustedAt,
    ) {
        if ($amount->isZero()) {
            throw InvalidAdjustment::zeroAmount();
        }
    }
}
