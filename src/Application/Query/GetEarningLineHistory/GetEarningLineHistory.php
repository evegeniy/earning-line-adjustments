<?php

declare(strict_types=1);

namespace Payroll\Application\Query\GetEarningLineHistory;

final readonly class GetEarningLineHistory
{
    public function __construct(
        public string $lineId,
    ) {}
}
