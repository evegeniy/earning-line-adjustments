<?php

declare(strict_types=1);

namespace Payroll\Application\Query\GetEarningLineHistory;

use Payroll\Application\ReadModel\EarningLineHistory;
use Payroll\Application\ReadModel\EarningLineHistoryStore;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\Exception\EarningLineNotFound;

final readonly class GetEarningLineHistoryHandler
{
    public function __construct(
        private EarningLineHistoryStore $histories,
    ) {}

    public function __invoke(GetEarningLineHistory $query): EarningLineHistory
    {
        return $this->histories->find($query->lineId)
            ?? throw EarningLineNotFound::withId(new EarningLineId($query->lineId));
    }
}
