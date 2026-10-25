<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Persistence;

use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\EarningLineRepository;
use Payroll\Domain\Shared\ConcurrencyConflict;
use WeakMap;

/**
 * In-memory storage for the demo and the tests that behaves like a real database with an ORM:
 *
 *  - find() always returns a fresh, independent instance, never a shared reference;
 *  - save() writes nothing when the instance has not changed since it was loaded;
 *  - save() uses optimistic locking, so a stale instance cannot overwrite newer data.
 *
 * The last two protect business rules: a system recalculation that loaded the line just before
 * a specialist's first adjustment must not save over it and undo the freeze, while an ignored
 * recalculation must not make a specialist's concurrent adjustment fail.
 */
final class InMemoryEarningLineRepository implements EarningLineRepository
{
    /** @var array<string, array{version: positive-int, snapshot: string}> */
    private array $records = [];

    /** @var WeakMap<EarningLine, array{version: positive-int, snapshot: string}> record each instance in memory is based on */
    private WeakMap $loadedFrom;

    public function __construct()
    {
        $this->loadedFrom = new WeakMap();
    }

    public function find(EarningLineId $id): ?EarningLine
    {
        $record = $this->records[$id->value] ?? null;

        if ($record === null) {
            return null;
        }

        $line = unserialize($record['snapshot'], ['allowed_classes' => true]);
        \assert($line instanceof EarningLine);
        $this->loadedFrom[$line] = $record;

        return $line;
    }

    public function save(EarningLine $line): void
    {
        $id = $line->id()->value;
        $snapshot = serialize($line);
        $loadedFrom = $this->loadedFrom[$line] ?? null;

        if ($snapshot === ($loadedFrom['snapshot'] ?? null)) {
            return;
        }

        $storedVersion = $this->records[$id]['version'] ?? 0;

        if (($loadedFrom['version'] ?? 0) !== $storedVersion) {
            throw ConcurrencyConflict::onAggregate('Earning line', $id);
        }

        $record = ['version' => $storedVersion + 1, 'snapshot' => $snapshot];
        $this->records[$id] = $record;
        $this->loadedFrom[$line] = $record;
    }
}
