<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine\Exception;

use Payroll\Domain\EarningLine\EarningLineId;
use RuntimeException;

final class EarningLineNotFound extends RuntimeException
{
    public static function withId(EarningLineId $id): self
    {
        return new self(sprintf('Earning line "%s" does not exist.', $id->value));
    }
}
