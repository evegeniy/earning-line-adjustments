<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use InvalidArgumentException;

/**
 * The payroll specialist who made a manual adjustment; recorded for traceability.
 */
final readonly class SpecialistId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Specialist id must not be empty.');
        }
    }
}
