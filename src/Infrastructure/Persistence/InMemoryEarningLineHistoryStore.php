<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Persistence;

use Payroll\Application\ReadModel\EarningLineHistory;
use Payroll\Application\ReadModel\EarningLineHistoryStore;

final class InMemoryEarningLineHistoryStore implements EarningLineHistoryStore
{
    /** @var array<string, EarningLineHistory> */
    private array $histories = [];

    public function find(string $lineId): ?EarningLineHistory
    {
        return $this->histories[$lineId] ?? null;
    }

    public function save(EarningLineHistory $history): void
    {
        $this->histories[$history->lineId] = $history;
    }
}
