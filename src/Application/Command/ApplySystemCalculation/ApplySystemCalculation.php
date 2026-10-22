<?php

declare(strict_types=1);

namespace Payroll\Application\Command\ApplySystemCalculation;

/**
 * The payroll engine calculated a line from its source data, either for the first time
 * or again after the source data changed.
 */
final readonly class ApplySystemCalculation
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $currency,
    ) {}
}
