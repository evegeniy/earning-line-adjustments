<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

use Payroll\Domain\Shared\Money;

/**
 * Audit view of an earning line: the system value (frozen once adjusted), every manual
 * adjustment in order, and the current value they produce.
 */
final readonly class EarningLineHistory
{
    public Money $currentValue;

    /**
     * @param list<AdjustmentEntry> $adjustments
     */
    public function __construct(
        public string $lineId,
        public Money $systemValue,
        public array $adjustments = [],
    ) {
        $this->currentValue = array_reduce(
            $adjustments,
            static fn(Money $value, AdjustmentEntry $entry): Money => $value->add($entry->amount),
            $systemValue,
        );
    }

    public function isSystemValueFrozen(): bool
    {
        return $this->adjustments !== [];
    }

    public function withSystemValue(Money $systemValue): self
    {
        return new self($this->lineId, $systemValue, $this->adjustments);
    }

    public function withAdjustment(AdjustmentEntry $entry): self
    {
        // Events may be delivered more than once; an adjustment already in the history is skipped.
        if ($entry->sequence <= \count($this->adjustments)) {
            return $this;
        }

        return new self($this->lineId, $this->systemValue, [...$this->adjustments, $entry]);
    }
}
