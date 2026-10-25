# Earning Line: Manual Adjustments History

A plain object-oriented domain model with state-based persistence: no Event Sourcing, event bus or
separate read model, see [why](#why-not-event-sourcing). Plain PHP 8.3, no framework. PHPUnit,
PHPStan `level: max`, CI.

## Quick start

```bash
composer install
composer demo    # replays the 8 steps from the assignment
composer check   # code style + PHPStan + tests
```

Docker only: `docker run --rm -it -v "$PWD":/app -w /app composer:2 sh -c "composer install && composer check && php bin/demo"`

The demo ends with the expected audit history:

```text
Entry                       Value   Comment                                                 By             At (UTC)
---------------------   ---------   -----------------------------------------------------   ------------   ----------------
System value (frozen)   $1,050.00
Adjustment 1              -$45.55   Employee declined dental benefit; reversing deduction   specialist-1   2026-10-25 18:13
Adjustment 2             +$100.10   Late correction: missed approved overtime bonus         specialist-1   2026-10-25 18:13
Adjustment 3               -$0.10   Minor rounding adjustment                               specialist-1   2026-10-25 18:13
Adjustment 4               -$0.20   Second minor rounding adjustment                        specialist-1   2026-10-25 18:13
Adjustment 5               +$0.20   Correcting mistake in adjustment #4                     specialist-1   2026-10-25 18:13
Current value           $1,104.45
```

The same scenario, with the value after every step, is checked by
[`ManualAdjustmentsBusinessCaseTest`](tests/Acceptance/ManualAdjustmentsBusinessCaseTest.php).

## How the business rules are enforced

| Rule | How |
|---|---|
| Non-zero amount with a mandatory comment | `ManualAdjustment`, `AdjustmentComment`. `Money` stores integer cents and accepts only decimal strings, never floats. |
| Corrections are never edited or deleted | `EarningLine` keeps an append-only list of `readonly` adjustments (number, amount, comment, author, time) and has no method to change one. Mistakes are fixed by a compensating adjustment. |
| After the first correction, recalculation is ignored | `EarningLine::recalculate()` keeps the frozen value. Optimistic locking stops a stale recalculation from overwriting the first correction (see Concurrency below). |
| Current value and full history at any time | `currentValue()` is derived (frozen system value + Σ adjustments), never stored. The `GetEarningLineHistory` query returns the line with its frozen system value and every adjustment in order. |

## Design

```text
src/Domain/          EarningLine aggregate, value objects, repository interface, Money
src/Application/     Command and query handlers
src/Infrastructure/  In-memory storage, clock, composition root
src/UI/Cli/          Demo output
```

- **Commands:** `ApplySystemCalculation` (first calculation or recalculation; the domain decides
  whether to apply it) and `AddManualAdjustment`. Handlers load the line, call the domain and save it.
- **Query:** `GetEarningLineHistory` returns the line itself. The history *is* its state, so a
  separate read model would only duplicate it.
- **One aggregate.** Adjustments live inside `EarningLine`, because the freeze rule spans both
  the system value and the adjustments, and an adjustment has no lifecycle outside its line.
- **Concurrency.** A recalculation that loaded the line before the first correction must not save
  over it. Optimistic locking rejects that write with `ConcurrencyConflict`. An ignored
  recalculation writes nothing, so it never causes a conflict for a specialist.

### Why not Event Sourcing

The required audit trail is the history of corrections, and here it is domain state: an
append-only list protected by the aggregate. That satisfies every rule without an event store,
projections or a message bus, and keeps one source of truth for the current value. Domain events
were left out because nothing in this PoC consumes them.

Moving to DDD + CQRS + ES would be mechanical: each method records an event and applies it, the
repository becomes event-sourced with the same expected-version check, and a projector builds the
history view. One trade-off: ES would also keep the recalculations made before the freeze
(1000 → 1050); here only the latest system value is stored.

## Assumptions

1. A correction must be non-zero, in the line's currency, with a comment of at most 500 chars.
   A recalculation in another currency is rejected even after the freeze: it points to bad source data.
2. The freeze is permanent: there is no "reset to system value".
3. The current value may become negative (e.g. a clawback); payroll-run totals are validated elsewhere.
4. Ignored recalculations are not recorded, as in the assignment's expected output.
5. A compensating correction refers to the corrected one through its comment, as in the example.
6. Line identity (employee, period, earning code) and authorization are owned upstream.

## Next steps for production

- DB with an append-only `earning_line_adjustments` table (no `UPDATE`/`DELETE` privileges) and a
  `version` column on the line.
- Retry on `ConcurrencyConflict` (reload the line and re-run the command).
- Tell the specialist when source data changed after the line was frozen. That is the first real
  consumer that would justify domain events (published through a transactional outbox).
