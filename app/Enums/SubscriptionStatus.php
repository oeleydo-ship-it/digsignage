<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Canceled => 'Canceled',
            self::Unpaid => 'Unpaid',
            self::Incomplete => 'Incomplete',
        };
    }

    public function allowsMutations(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }
}
