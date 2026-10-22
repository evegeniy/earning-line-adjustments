<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Exception;

use DomainException;

final class InvalidAdjustment extends DomainException
{
    public static function zeroAmount(): self
    {
        return new self('A manual adjustment must have a non-zero amount.');
    }

    public static function missingComment(): self
    {
        return new self('A manual adjustment must have a comment explaining why it was made.');
    }

    public static function commentTooLong(int $maxLength): self
    {
        return new self(sprintf('An adjustment comment must not be longer than %d characters.', $maxLength));
    }
}
