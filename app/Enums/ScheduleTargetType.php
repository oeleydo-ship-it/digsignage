<?php

namespace App\Enums;

enum ScheduleTargetType: string
{
    case Screen = 'screen';
    case ScreenGroup = 'screen_group';
    case Location = 'location';

    /**
     * Get the display label for the target type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Screen => 'Screen',
            self::ScreenGroup => 'Screen group',
            self::Location => 'Location',
        };
    }

    /**
     * Specificity used when two schedules share a priority.
     */
    public function specificity(): int
    {
        return match ($this) {
            self::Screen => 3,
            self::ScreenGroup => 2,
            self::Location => 1,
        };
    }
}
