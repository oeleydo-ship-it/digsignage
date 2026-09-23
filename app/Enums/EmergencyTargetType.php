<?php

namespace App\Enums;

enum EmergencyTargetType: string
{
    case Screen = 'screen';
    case Location = 'location';

    /**
     * Get the display label for the target type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Screen => 'Screen',
            self::Location => 'Location',
        };
    }
}
