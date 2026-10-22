<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\Event\EarningLineCalculated;
use Payroll\Domain\EarningLine\Event\EarningLineRecalculated;
use Payroll\Domain\EarningLine\Event\ManualAdjustmentAdded;
use Payroll\Domain\EarningLine\Event\SystemRecalculationIgnored;
use Payroll\Domain\Shared\AggregateRoot;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\CurrencyMismatch;
use Payroll\Domain\Shared\Money;

/**
 * A payroll earning line, e.g. an employee's base salary for a pay period.
 *
 * Invariants protected by this aggregate:
 *  - Until the first manual adjustment the value is owned by the system and follows every recalculation.
 *  - The first manual adjustment freezes the system value: later recalculations are ignored for good.
 *  - Manual adjustments are append-only; there is no API to edit or remove one.
 *  - The current value is always the (frozen) system value plus the sum of all adjustments.
 *  - Every amount is in the line's currency.
 */
final class EarningLine extends AggregateRoot
{
    /** @var list<ManualAdjustment> */
    private array $adjustments = [];

    private function __construct(
        private readonly EarningLineId $id,
        private Money $systemValue,
    ) {}

    public static function calculate(EarningLineId $id, Money $systemValue, DateTimeImmutable $calculatedAt): self
    {
        $line = new self($id, $systemValue);
        $line->recordThat(new EarningLineCalculated($id, $systemValue, $calculatedAt));

        return $line;
    }

    /**
     * Applies a value the system calculated from (possibly changed) source data.
     *
     * Once a specialist has adjusted the line, their corrections take permanent precedence:
     * the recalculation is ignored, but still recorded as an event so it stays observable.
     */
    public function recalculate(Money $systemValue, DateTimeImmutable $calculatedAt): void
    {
        if ($this->isManuallyAdjusted()) {
            $this->recordThat(new SystemRecalculationIgnored($this->id, $systemValue, $calculatedAt));

            return;
        }

        $this->assertLineCurrency($systemValue);

        if ($systemValue->equals($this->systemValue)) {
            return;
        }

        $previousValue = $this->systemValue;
        $this->systemValue = $systemValue;

        $this->recordThat(new EarningLineRecalculated($this->id, $previousValue, $systemValue, $calculatedAt));
    }

    public function addManualAdjustment(
        Money $amount,
        AdjustmentComment $comment,
        SpecialistId $adjustedBy,
        DateTimeImmutable $adjustedAt,
    ): void {
        $this->assertLineCurrency($amount);

        $adjustment = new ManualAdjustment(\count($this->adjustments) + 1, $amount, $comment, $adjustedBy, $adjustedAt);
        $this->adjustments[] = $adjustment;

        $this->recordThat(new ManualAdjustmentAdded(
            $this->id,
            $adjustment->sequence,
            $adjustment->amount,
            $adjustment->comment,
            $adjustment->adjustedBy,
            $adjustment->adjustedAt,
        ));
    }

    public function id(): EarningLineId
    {
        return $this->id;
    }

    public function currency(): Currency
    {
        return $this->systemValue->currency;
    }

    /**
     * The value calculated by the system; frozen as of the first manual adjustment.
     */
    public function systemValue(): Money
    {
        return $this->systemValue;
    }

    public function isManuallyAdjusted(): bool
    {
        return $this->adjustments !== [];
    }

    /**
     * @return list<ManualAdjustment> in the order they were added
     */
    public function adjustments(): array
    {
        return $this->adjustments;
    }

    public function currentValue(): Money
    {
        return array_reduce(
            $this->adjustments,
            static fn(Money $value, ManualAdjustment $adjustment): Money => $value->add($adjustment->amount),
            $this->systemValue,
        );
    }

    private function assertLineCurrency(Money $amount): void
    {
        if ($amount->currency !== $this->currency()) {
            throw CurrencyMismatch::between($this->currency(), $amount->currency);
        }
    }
}
