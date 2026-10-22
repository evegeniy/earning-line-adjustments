<?php

declare(strict_types=1);

namespace Payroll\UI\Cli;

use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;

final class MoneyFormatter
{
    /**
     * Formats for humans, e.g. "$1,050.00", "-$45.55", or "+$100.10" when the sign matters.
     */
    public static function format(Money $money, bool $withSign = false): string
    {
        [$units, $fraction] = explode('.', ltrim($money->toDecimalString(), '-'));

        $sign = match (true) {
            $money->minorAmount < 0 => '-',
            $withSign && $money->minorAmount > 0 => '+',
            default => '',
        };

        return $sign . self::symbol($money->currency) . number_format((int) $units) . '.' . $fraction;
    }

    private static function symbol(Currency $currency): string
    {
        return match ($currency) {
            Currency::USD => '$',
            Currency::EUR => '€',
            Currency::GBP => '£',
        };
    }
}
