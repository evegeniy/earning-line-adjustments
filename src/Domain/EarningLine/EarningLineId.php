<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use InvalidArgumentException;

/**
 * Identity of an earning line. Assigned upstream by the payroll run, so it is treated as opaque.
 */
final readonly class EarningLineId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Earning line id must not be empty.');
        }
    }
}
