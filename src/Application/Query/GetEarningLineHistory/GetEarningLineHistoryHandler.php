<?php

declare(strict_types=1);

namespace Payroll\Application\Query\GetEarningLineHistory;

use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\EarningLineRepository;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;

/**
 * The line itself is the audit view: its (frozen) system value, every adjustment in order
 * and the current value they produce. A separate read model would only duplicate that state.
 */
final readonly class GetEarningLineHistoryHandler
{
    public function __construct(
        private EarningLineRepository $lines,
    ) {}

    public function __invoke(GetEarningLineHistory $query): EarningLine
    {
        $id = new EarningLineId($query->lineId);

        return $this->lines->find($id) ?? throw EarningLineNotFound::withId($id);
    }
}
