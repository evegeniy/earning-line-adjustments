<?php

declare(strict_types=1);

namespace Payroll\Tests\Support;

use Payroll\Application\EventBus;
use Payroll\Domain\Shared\DomainEvent;

final class RecordingEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    private array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        array_push($this->published, ...$events);
    }

    /**
     * @return list<DomainEvent>
     */
    public function published(): array
    {
        return $this->published;
    }
}
