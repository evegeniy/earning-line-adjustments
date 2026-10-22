<?php

declare(strict_types=1);

namespace Payroll\Application;

use Payroll\Domain\Shared\DomainEvent;

interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
