<?php

declare(strict_types=1);

namespace Payroll\Domain\Shared;

abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    final protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Hands over the events recorded since the last call and forgets them,
     * so that every event is published exactly once.
     *
     * @return list<DomainEvent>
     */
    final public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
