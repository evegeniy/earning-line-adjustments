<?php

declare(strict_types=1);

namespace Payroll\Application;

use Payroll\Domain\Shared\DomainEvent;

interface EventSubscriber
{
    public function __invoke(DomainEvent $event): void;
}
