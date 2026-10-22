<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

use DateTimeImmutable;
use Payroll\Domain\Shared\Money;

final readonly class AdjustmentEntry
{
    /**
     * @param positive-int $sequence
     */
    public function __construct(
        public int $sequence,
        public Money $amount,
        public string $comment,
        public string $adjustedBy,
        public DateTimeImmutable $adjustedAt,
    ) {}
}
