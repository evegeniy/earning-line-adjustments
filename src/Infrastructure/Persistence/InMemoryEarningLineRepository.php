<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Persistence;

use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\EarningLineRepository;
use Payroll\Domain\Shared\ConcurrencyConflict;
use WeakMap;

/**
 * In-memory storage for the demo and the tests that behaves like a real database:
 *
 *  - find() always returns a fresh, independent instance, never a shared reference;
 *  - save() uses optimistic locking, so a stale instance cannot overwrite newer data.
 *
 * The latter protects a business rule: a system recalculation that loaded the line just
 * before a specialist's first adjustment must not be able to save over it and undo the freeze.
 */
final class InMemoryEarningLineRepository implements EarningLineRepository
{
    /** @var array<string, array{version: positive-int, snapshot: string}> */
    private array $records = [];

    /** @var WeakMap<EarningLine, positive-int> stored version each instance in memory is based on */
    private WeakMap $loadedVersions;

    public function __construct()
    {
        $this->loadedVersions = new WeakMap();
    }

    public function find(EarningLineId $id): ?EarningLine
    {
        $record = $this->records[$id->value] ?? null;

        if ($record === null) {
            return null;
        }

        $line = unserialize($record['snapshot'], ['allowed_classes' => true]);
        \assert($line instanceof EarningLine);
        $this->loadedVersions[$line] = $record['version'];

        return $line;
    }

    public function save(EarningLine $line): void
    {
        $id = $line->id()->value;
        $storedVersion = $this->records[$id]['version'] ?? 0;

        if (($this->loadedVersions[$line] ?? 0) !== $storedVersion) {
            throw ConcurrencyConflict::onAggregate('Earning line', $id);
        }

        // Pending events are not state; a real database would not store them either.
        $snapshot = clone $line;
        $snapshot->releaseEvents();

        $this->records[$id] = ['version' => $storedVersion + 1, 'snapshot' => serialize($snapshot)];
        $this->loadedVersions[$line] = $storedVersion + 1;
    }
}
