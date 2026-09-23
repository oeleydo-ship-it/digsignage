<?php

namespace App\Enums;

enum QueueAppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::CheckedIn => 'Checked in',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }
}
