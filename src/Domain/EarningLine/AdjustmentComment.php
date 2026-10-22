<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use Payroll\Domain\EarningLine\Exception\InvalidAdjustment;

/**
 * The mandatory explanation of why a manual adjustment was made.
 */
final readonly class AdjustmentComment
{
    public const int MAX_LENGTH = 500;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidAdjustment::missingComment();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidAdjustment::commentTooLong(self::MAX_LENGTH);
        }

        $this->value = $value;
    }
}
