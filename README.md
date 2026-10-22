# Earning Line: Manual Adjustments History

A proof of concept for Alcor OS payroll. A payroll specialist can manually correct an
automatically calculated earning line. Every correction is kept as an audit trail, and once
a line has a correction, system recalculations no longer change it.

The solution uses **DDD + CQRS** with **state-based persistence and domain events**. It does
not use Event Sourcing; [why](#why-not-event-sourcing) is explained below, together with the
migration path to ES.

- Plain PHP 8.3, no framework. Composer dependencies: only `psr/clock` at runtime, plus dev tools.
- 72 PHPUnit tests, PHPStan at `level: max`, PER-CS code style, CI on PHP 8.3 / 8.4 / 8.5.

## Quick start

Requirements: PHP 8.3+ and Composer, or just Docker.

```bash
composer install
composer demo    # replays the business case from the assignment
composer test    # PHPUnit
composer check   # code style + PHPStan + tests
```

With Docker instead of a local PHP:

```bash
docker run --rm -it -v "$PWD":/app -w /app composer:2 sh -c "composer install && composer check && php bin/demo"
```

`composer demo` runs the 8 steps from the assignment through the application's commands and query:

```text
1. System calculates the line (1000.00 USD)
   event: EarningLineCalculated
   current value: $1,000.00

2. Source data changes, system recalculates (1050.00 USD)
   event: EarningLineRecalculated
   current value: $1,050.00

3. Specialist adds a manual correction (-45.55 USD)
   event: ManualAdjustmentAdded
   current value: $1,004.45

4. Source data changes again, system attempts to recalculate (1100.00 USD)
   event: SystemRecalculationIgnored
   current value: $1,004.45
...
8. Specialist adds a compensating correction (+0.20 USD)
   event: ManualAdjustmentAdded
   current value: $1,104.45

Audit history of earning line "base-salary-2025-01":

Entry                       Value   Comment                                                 By             At (UTC)
---------------------   ---------   -----------------------------------------------------   ------------   ----------------
System value (frozen)   $1,050.00
Adjustment 1              -$45.55   Employee declined dental benefit; reversing deduction   specialist-1   2026-10-22 08:02
Adjustment 2             +$100.10   Late correction: missed approved overtime bonus         specialist-1   2026-10-22 08:02
Adjustment 3               -$0.10   Minor rounding adjustment                               specialist-1   2026-10-22 08:02
Adjustment 4               -$0.20   Second minor rounding adjustment                        specialist-1   2026-10-22 08:02
Adjustment 5               +$0.20   Correcting mistake in adjustment #4                     specialist-1   2026-10-22 08:02
Current value           $1,104.45
```

The same scenario is an automated test:
[`tests/Acceptance/ManualAdjustmentsBusinessCaseTest.php`](tests/Acceptance/ManualAdjustmentsBusinessCaseTest.php).
It checks the current value after every step and the final audit history.

## How the business rules are enforced

| Business rule | Where and how |
|---|---|
| A correction is a positive or negative amount with a mandatory comment | `ManualAdjustment` rejects a zero amount. `AdjustmentComment` is required (trimmed, max 500 chars). `Money` accepts only decimal strings and stores integer cents, so no floats are involved. |
| A line can receive many corrections over time, each visible and traceable | `EarningLine` keeps an append-only `list<ManualAdjustment>`. Each adjustment records its sequence number (`#4`), amount, comment, author and time. |
| A correction can never be edited or silently deleted | `ManualAdjustment` is a `readonly` class, and `EarningLine` has no method that changes or removes one. A mistake is fixed by adding a compensating adjustment (step 8). |
| After the first correction, recalculation no longer affects the line, permanently | `EarningLine::recalculate()` ignores the new value once the line is adjusted and records a `SystemRecalculationIgnored` event. Optimistic locking makes sure a concurrent recalculation cannot overwrite the first correction (see [Concurrency](#concurrency)). |
| The current value and the full history are available at any time | Write side: `EarningLine::currentValue()` is derived as *frozen system value + Σ adjustments* and never stored, so it cannot drift. Read side: the `GetEarningLineHistory` query returns the `EarningLineHistory` read model. |

## Design

### Layers

```text
src/
├── Domain/          Business rules only. No framework or infrastructure dependencies.
│   ├── EarningLine/   Aggregate, value objects, events, exceptions, repository interface
│   └── Shared/        Money, Currency, AggregateRoot, DomainEvent, ConcurrencyConflict
├── Application/     Use cases: commands, queries, read model, ports (EventBus, stores)
├── Infrastructure/  Adapters: in-memory storage, synchronous event bus, clock, composition root
└── UI/Cli/          Presentation for the demo (money formatting, history table)
```

Dependencies point inwards only: UI → Application → Domain, and Infrastructure implements the
interfaces defined by the inner layers. Every collaborator is injected through the constructor.
[`InMemoryContainer`](src/Infrastructure/InMemoryContainer.php) is the single composition root.

### Write side, read side

```text
 ApplySystemCalculation ─┐
 AddManualAdjustment ────┴─> command handler ─> EarningLine (aggregate)
                                  │                  │ records domain events
                                  ├─> EarningLineRepository::save()      (optimistic locking)
                                  └─> EventBus::publish(events) ─> EarningLineHistoryProjector
                                                                             │
 GetEarningLineHistory ─> query handler ─> EarningLineHistoryStore <─────────┘
                               └─> EarningLineHistory (read model)
```

- **Commands**
  - `ApplySystemCalculation` covers both the first calculation and later recalculations. The
    payroll engine does not need to know whether a line exists or is frozen, because that
    decision belongs to the domain.
  - `AddManualAdjustment` is the specialist's correction.
- **Query**: `GetEarningLineHistory` reads only the projected read model and never loads the aggregate.
- **Events**: `EarningLineCalculated`, `EarningLineRecalculated`, `ManualAdjustmentAdded`,
  `SystemRecalculationIgnored`. The projector is idempotent for redelivered adjustment events.
- Handlers are plain invokable classes. In a real application, a bus such as Symfony Messenger
  would route commands and queries to them.

### Aggregate boundary

`EarningLine` is the only aggregate, and the manual adjustments are part of it rather than
aggregates of their own:

- The key invariant ("the first correction freezes the system value; later recalculations are
  ignored") involves both the system value and the adjustments, so both must change in the
  same consistency boundary.
- An adjustment has no lifecycle outside its line. Its identity is local: "adjustment #4 of
  this line".
- A line receives a handful of corrections, not thousands, so loading them with the aggregate
  is cheap.

### Why not Event Sourcing

The requirement is an audit trail of **corrections**. This model treats that history as
**domain state**: an append-only collection of immutable value objects that the aggregate
protects. The audit trail is a business concept here, independent of how the data is
persisted. That is enough to meet every rule. Event Sourcing would add an event store, stream
versioning, upcasting, snapshots and projection rebuilds, and a proof of concept does not need
any of that to prove these rules.

The model is built so that moving to ES is a mechanical step rather than a redesign:

- Every state change already produces a domain event that carries all the data needed to
  rebuild state, and the aggregate already records events (`AggregateRoot::recordThat()`).
- To switch, command methods would only record events, `apply*()` methods would mutate state,
  and the repository would load by replaying the stream and save by appending with an expected
  version. This is the same optimistic concurrency check the repository already does.
- The read side would not change: the projector already builds the history from events only.

One trade-off: with ES, the history of system recalculations made *before* the freeze (step 2:
1000 → 1050) would be kept automatically. Here those changes are published as events, but the
aggregate keeps only the latest system value. The assignment's expected history does not
include them, so for this use case that is acceptable.

### Concurrency

There is a race that would break the "permanent precedence" rule:

1. A recalculation job loads the line. It has no corrections yet.
2. A specialist loads the same line, adds the first correction and saves. The system value is now frozen at 1050.
3. The job saves a new system value of 1100 over that state, and the freeze is lost.

The repository uses optimistic locking, so step 3 fails with `ConcurrencyConflict` (retryable).
After a reload, the recalculation is correctly ignored. In the other direction, an ignored
recalculation changes nothing, so the repository writes nothing (as an ORM with change tracking
would). It therefore never causes a conflict for a specialist working at the same time.
Both cases are covered in
[`InMemoryEarningLineRepositoryTest`](tests/Unit/Infrastructure/Persistence/InMemoryEarningLineRepositoryTest.php).

### Money

Amounts are stored as integer minor units and parsed from decimal strings. Floats are rejected
on purpose, because `0.1 + 0.2 !== 0.3` is not acceptable in payroll. Amounts in different
currencies cannot be added together. In production I would use `brick/money` or `moneyphp/money`
instead of a hand-rolled value object.

## Assumptions

1. **A correction must be non-zero.** "Positive or negative" is read as excluding zero, because
   a zero correction carries no information. The comment is trimmed, required and at most 500
   characters.
2. **A correction is in the line's currency.** All supported currencies have two decimal places.
3. **The current value may become negative** (for example, a clawback). Validating the totals of
   a payroll run belongs to a higher level than a single line.
4. **The freeze is permanent.** There is no "unfreeze" or "reset to system value". If changed
   source data matters, the specialist adds another correction.
5. **Ignored recalculations are not part of the line's audit history**, matching the expected
   history in the assignment. They are still published as `SystemRecalculationIgnored` events,
   so they can be logged or shown to the specialist.
6. **A compensating correction references the corrected one through its comment** ("Correcting
   mistake in adjustment #4"), as in the example. Sequence numbers make adjustments easy to
   reference. A structured `corrects: #4` link would be a small addition.
7. **A recalculation to the same value is a no-op** and records no event.
8. **The identity of a line** (employee, pay period, earning code) is owned upstream by the
   payroll run, so the line ID is treated as opaque.
9. **Authentication and authorization are out of scope.** The specialist ID comes with the
   command. Time comes from an injected PSR-20 clock, in UTC.

## What I would do next for production

- **Persistence.** Use two tables: `earning_lines` (id, currency, system_value, version) and an
  append-only `earning_line_adjustments` table with primary key `(line_id, sequence)`. Enforce
  append-only in the database as well: the application user gets no `UPDATE`/`DELETE`
  privileges, or a trigger rejects those statements.
- **Transactional outbox.** Store state and events atomically, then project asynchronously.
  The projector already tolerates redelivery.
- **Buses.** Symfony Messenger for commands, queries and events, with automatic retry on
  `ConcurrencyConflict`.
- **Product.** Show the specialist that the source data changed after the line was frozen,
  based on `SystemRecalculationIgnored`. Add an explicit link between a compensating
  correction and the one it fixes.

## Tests

```text
tests/
├── Acceptance/  The assignment's business case, end to end (commands in, query out)
├── Unit/        Domain rules, handlers, projector, repository concurrency, CLI rendering
└── Support/     Test doubles: TestClock (PSR-20), RecordingEventBus
```

Tests run in random order (`executionOrder="random"`) to catch hidden coupling between them.

## Use of AI tools

As the assignment encourages, this solution was built with an AI pair programmer (Claude Code).
