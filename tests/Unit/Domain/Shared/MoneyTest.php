<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Domain\Shared;

use InvalidArgumentException;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\CurrencyMismatch;
use Payroll\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function validAmounts(): iterable
    {
        yield 'whole number' => ['1050', 105000];
        yield 'two decimals' => ['1050.00', 105000];
        yield 'one decimal' => ['0.2', 20];
        yield 'negative' => ['-45.55', -4555];
        yield 'explicit plus' => ['+100.10', 10010];
        yield 'smallest unit' => ['-0.01', -1];
        yield 'negative zero' => ['-0.00', 0];
    }

    #[DataProvider('validAmounts')]
    public function testParsesDecimalStringsWithoutFloatingPointErrors(string $amount, int $expectedMinorAmount): void
    {
        self::assertSame($expectedMinorAmount, Money::of($amount, Currency::USD)->minorAmount);
    }

    #[TestWith([''])]
    #[TestWith(['abc'])]
    #[TestWith(['1.005'])]
    #[TestWith(['1,050.00'])]
    #[TestWith(['.5'])]
    #[TestWith([' 10'])]
    #[TestWith(['1e3'])]
    public function testRejectsMalformedAmounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of($amount, Currency::USD);
    }

    public function testAddsAmountsOfTheSameCurrency(): void
    {
        $sum = Money::of('1050.00', Currency::USD)->add(Money::of('-45.55', Currency::USD));

        self::assertEquals(Money::of('1004.45', Currency::USD), $sum);
    }

    public function testAdditionDoesNotSufferFromFloatingPointRounding(): void
    {
        $sum = Money::of('0.10', Currency::USD)->add(Money::of('0.20', Currency::USD));

        self::assertTrue($sum->equals(Money::of('0.30', Currency::USD)));
    }

    public function testRefusesToAddDifferentCurrencies(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::of('10.00', Currency::USD)->add(Money::of('10.00', Currency::EUR));
    }

    public function testEqualityTakesCurrencyIntoAccount(): void
    {
        self::assertTrue(Money::of('10', Currency::USD)->equals(Money::ofMinor(1000, Currency::USD)));
        self::assertFalse(Money::of('10', Currency::USD)->equals(Money::of('10', Currency::EUR)));
        self::assertFalse(Money::of('10', Currency::USD)->equals(Money::of('10.01', Currency::USD)));
    }

    public function testKnowsWhenItIsZero(): void
    {
        self::assertTrue(Money::of('0.00', Currency::USD)->isZero());
        self::assertFalse(Money::of('-0.01', Currency::USD)->isZero());
    }

    #[TestWith([105000, '1050.00'])]
    #[TestWith([-4555, '-45.55'])]
    #[TestWith([-20, '-0.20'])]
    #[TestWith([5, '0.05'])]
    #[TestWith([0, '0.00'])]
    public function testFormatsAsPlainDecimalString(int $minorAmount, string $expected): void
    {
        self::assertSame($expected, Money::ofMinor($minorAmount, Currency::USD)->toDecimalString());
    }
}
