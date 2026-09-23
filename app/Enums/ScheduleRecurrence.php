<?php

namespace App\Enums;

enum ScheduleRecurrence: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Get the display label for the recurrence.
     */
    public function label(): string
    {
        return match ($this) {
            self::Once => 'Once',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }
}
