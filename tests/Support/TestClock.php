<?php

declare(strict_types=1);

namespace Payroll\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class TestClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2025-01-31 09:00:00 UTC'),
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new DateInterval($interval));
    }
}
