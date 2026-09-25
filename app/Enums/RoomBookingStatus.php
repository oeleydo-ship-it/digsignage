<?php

namespace App\Enums;

enum RoomBookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting approval',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses that hold the room, so they block overlapping bookings.
     *
     * @return list<string>
     */
    public static function blocking(): array
    {
        return [self::Pending->value, self::Confirmed->value];
    }
}
