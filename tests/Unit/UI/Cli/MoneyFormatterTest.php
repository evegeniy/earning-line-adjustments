<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\UI\Cli;

use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\UI\Cli\MoneyFormatter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class MoneyFormatterTest extends TestCase
{
    #[TestWith(['1104.45', false, '$1,104.45'])]
    #[TestWith(['1234567.80', false, '$1,234,567.80'])]
    #[TestWith(['-45.55', false, '-$45.55'])]
    #[TestWith(['-45.55', true, '-$45.55'])]
    #[TestWith(['100.10', true, '+$100.10'])]
    #[TestWith(['0.00', true, '$0.00'])]
    public function testFormatsAmountsForHumans(string $amount, bool $withSign, string $expected): void
    {
        self::assertSame($expected, MoneyFormatter::format(Money::of($amount, Currency::USD), $withSign));
    }

    public function testUsesCurrencySymbol(): void
    {
        self::assertSame('€10.00', MoneyFormatter::format(Money::of('10', Currency::EUR)));
        self::assertSame('£10.00', MoneyFormatter::format(Money::of('10', Currency::GBP)));
    }
}
