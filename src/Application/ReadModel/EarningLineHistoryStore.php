<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

interface EarningLineHistoryStore
{
    public function find(string $lineId): ?EarningLineHistory;

    public function save(EarningLineHistory $history): void;
}
