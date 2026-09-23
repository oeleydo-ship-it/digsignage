<?php

namespace App\Enums;

enum QueueNumberingReset: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Never = 'never';

    /**
     * Get the display label for the reset policy.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Never => 'Never',
        };
    }
}
