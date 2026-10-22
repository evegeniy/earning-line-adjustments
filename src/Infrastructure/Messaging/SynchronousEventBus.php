<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Messaging;

use Payroll\Application\EventBus;
use Payroll\Application\EventSubscriber;
use Payroll\Domain\Shared\DomainEvent;

/**
 * Delivers events in-process to every subscriber, in the order they were recorded.
 * Good enough for a single-process proof of concept; see README for the production setup.
 */
final readonly class SynchronousEventBus implements EventBus
{
    /** @var list<EventSubscriber> */
    private array $subscribers;

    public function __construct(EventSubscriber ...$subscribers)
    {
        $this->subscribers = array_values($subscribers);
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            foreach ($this->subscribers as $subscriber) {
                $subscriber($event);
            }
        }
    }
}
