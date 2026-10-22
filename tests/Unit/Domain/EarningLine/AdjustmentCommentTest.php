<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Domain\EarningLine;

use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\Exception\InvalidAdjustment;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AdjustmentCommentTest extends TestCase
{
    public function testTrimsSurroundingWhitespace(): void
    {
        self::assertSame('Minor rounding adjustment', (new AdjustmentComment("  Minor rounding adjustment\n"))->value);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\n\t"])]
    public function testIsMandatory(string $comment): void
    {
        $this->expectExceptionObject(InvalidAdjustment::missingComment());

        new AdjustmentComment($comment);
    }

    public function testLengthIsLimitedInCharactersNotBytes(): void
    {
        $longest = str_repeat('ї', AdjustmentComment::MAX_LENGTH);

        self::assertSame($longest, (new AdjustmentComment($longest))->value);

        $this->expectExceptionObject(InvalidAdjustment::commentTooLong(AdjustmentComment::MAX_LENGTH));

        new AdjustmentComment($longest . 'ї');
    }
}
