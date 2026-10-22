<?php

declare(strict_types=1);

namespace Payroll\Domain\Shared;

use InvalidArgumentException;

/**
 * Immutable monetary amount.
 *
 * Stored as an integer number of minor units (cents) so that no floating point
 * rounding can ever creep into payroll numbers. Floats are deliberately not accepted
 * as input: amounts come in as decimal strings, e.g. "1050.00" or "-45.55".
 */
final readonly class Money
{
    private const int SCALE = 2;

    private const string DECIMAL_PATTERN = '/^(?<sign>[+-]?)(?<units>\d{1,15})(?:\.(?<fraction>\d{1,2}))?$/';

    private function __construct(
        public int $minorAmount,
        public Currency $currency,
    ) {}

    public static function ofMinor(int $minorAmount, Currency $currency): self
    {
        return new self($minorAmount, $currency);
    }

    public static function of(string $amount, Currency $currency): self
    {
        if (preg_match(self::DECIMAL_PATTERN, $amount, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid amount: expected a decimal number with at most %d decimal places.',
                $amount,
                self::SCALE,
            ));
        }

        $minorAmount = (int) $matches['units'] * 10 ** self::SCALE
            + (int) str_pad($matches['fraction'] ?? '', self::SCALE, '0');

        return new self($matches['sign'] === '-' ? -$minorAmount : $minorAmount, $currency);
    }

    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minorAmount === $other->minorAmount;
    }

    /**
     * Plain decimal representation without currency, e.g. "-45.55".
     */
    public function toDecimalString(): string
    {
        $absolute = abs($this->minorAmount);
        $divisor = 10 ** self::SCALE;

        return sprintf(
            '%s%d.%0' . self::SCALE . 'd',
            $this->minorAmount < 0 ? '-' : '',
            intdiv($absolute, $divisor),
            $absolute % $divisor,
        );
    }
}
