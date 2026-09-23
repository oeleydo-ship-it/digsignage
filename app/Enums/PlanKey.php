<?php

namespace App\Enums;

enum PlanKey: string
{
    case Starter = 'starter';
    case Business = 'business';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Business => 'Business',
            self::Enterprise => 'Enterprise',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Starter => 1,
            self::Business => 2,
            self::Enterprise => 3,
        };
    }
}
