<?php

declare(strict_types=1);

namespace Payroll\Domain\Shared;

use DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $expected, Currency $actual): self
    {
        return new self(sprintf('Expected an amount in %s, got %s.', $expected->value, $actual->value));
    }
}
