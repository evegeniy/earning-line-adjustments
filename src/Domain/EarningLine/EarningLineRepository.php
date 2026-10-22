<?php

declare(strict_types=1);

namespace Payroll\Domain\EarningLine;

use Payroll\Domain\Shared\ConcurrencyConflict;

interface EarningLineRepository
{
    public function find(EarningLineId $id): ?EarningLine;

    /**
     * @throws ConcurrencyConflict when the line was changed by someone else since it was loaded
     */
    public function save(EarningLine $line): void;
}
