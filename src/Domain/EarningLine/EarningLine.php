<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use DateTimeImmutable;
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
final class EarningLine
{
    /** @var list<ManualAdjustment> */
    private array $adjustments = [];

    private function __construct(
        private readonly EarningLineId $id,
        private Money $systemValue,
    ) {}

    public static function calculate(EarningLineId $id, Money $systemValue): self
    {
        return new self($id, $systemValue);
    }

    /**
     * Applies a value the system calculated from (possibly changed) source data.
     *
     * Once a specialist has adjusted the line, their corrections take permanent precedence
     * and the recalculation is ignored.
     */
    public function recalculate(Money $systemValue): void
    {
        $this->assertLineCurrency($systemValue);

        if ($this->isManuallyAdjusted()) {
            return;
        }

        $this->systemValue = $systemValue;
    }

    public function addManualAdjustment(
        Money $amount,
        AdjustmentComment $comment,
        SpecialistId $adjustedBy,
        DateTimeImmutable $adjustedAt,
    ): void {
        $this->assertLineCurrency($amount);

        $this->adjustments[] = new ManualAdjustment(\count($this->adjustments) + 1, $amount, $comment, $adjustedBy, $adjustedAt);
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
