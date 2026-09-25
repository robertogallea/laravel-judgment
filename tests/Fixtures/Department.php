<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

enum Department: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Other = 'other';

    public function description(): string
    {
        return match ($this) {
            self::Billing => 'Payments, invoices, refunds, subscriptions',
            self::Technical => 'Bugs, outages, integrations, errors',
            self::Other => 'Anything else',
        };
    }
}
