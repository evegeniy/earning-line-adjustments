<?php

declare(strict_types=1);

namespace Payroll\Domain\Shared;

/**
 * ISO 4217 currencies supported by the proof of concept.
 * All of them have two decimal places, which is what Money assumes.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
}
