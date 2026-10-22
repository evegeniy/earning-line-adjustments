<?php

declare(strict_types=1);

namespace Payroll\UI\Cli;

use Payroll\Application\ReadModel\EarningLineHistory;

/**
 * Renders the audit history of an earning line as a plain-text table.
 */
final class HistoryTable
{
    private const array HEADER = ['Entry', 'Value', 'Comment', 'By', 'At (UTC)'];

    private const int VALUE_COLUMN = 1;

    public static function render(EarningLineHistory $history): string
    {
        $rows = [[
            $history->isSystemValueFrozen() ? 'System value (frozen)' : 'System value',
            MoneyFormatter::format($history->systemValue),
            '',
            '',
            '',
        ]];

        foreach ($history->adjustments as $entry) {
            $rows[] = [
                'Adjustment ' . $entry->sequence,
                MoneyFormatter::format($entry->amount, withSign: true),
                $entry->comment,
                $entry->adjustedBy,
                $entry->adjustedAt->format('Y-m-d H:i'),
            ];
        }

        $rows[] = ['Current value', MoneyFormatter::format($history->currentValue), '', '', ''];

        $widths = array_map(
            static fn(int $column): int => max(array_map(mb_strlen(...), array_column([self::HEADER, ...$rows], $column))),
            array_keys(self::HEADER),
        );
        $separator = array_map(static fn(int $width): string => str_repeat('-', $width), $widths);

        $lines = array_map(
            static fn(array $row): string => self::renderRow($row, $widths),
            [self::HEADER, $separator, ...$rows],
        );

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, int> $widths
     */
    private static function renderRow(array $cells, array $widths): string
    {
        $padded = array_map(
            static fn(int $column): string => mb_str_pad(
                $cells[$column],
                $widths[$column],
                ' ',
                $column === self::VALUE_COLUMN ? STR_PAD_LEFT : STR_PAD_RIGHT,
            ),
            array_keys($cells),
        );

        return rtrim(implode('   ', $padded));
    }
}
