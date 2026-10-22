<?php

declare(strict_types=1);

namespace Payroll\Domain\Shared;

use RuntimeException;

/**
 * An aggregate was modified concurrently. Safe to retry: reload the aggregate and re-run the command.
 */
final class ConcurrencyConflict extends RuntimeException
{
    public static function onAggregate(string $aggregateType, string $id): self
    {
        return new self(sprintf('%s "%s" was modified concurrently, reload it and try again.', $aggregateType, $id));
    }
}
