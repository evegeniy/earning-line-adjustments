<?php

declare(strict_types=1);

namespace Payroll\Application\Command\AddManualAdjustment;

/**
 * A payroll specialist corrects a line by a positive or negative amount, e.g. "-45.55".
 */
final readonly class AddManualAdjustment
{
    public function __construct(
        public string $lineId,
        public string $amount,
        public string $currency,
        public string $comment,
        public string $specialistId,
    ) {}
}
