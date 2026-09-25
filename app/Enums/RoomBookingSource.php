<?php

namespace App\Enums;

enum RoomBookingSource: string
{
    case Manual = 'manual';
    case PublicForm = 'public_form';
    case Microsoft365 = 'microsoft365';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Booked by staff',
            self::PublicForm => 'Booking form',
            self::Microsoft365 => 'Microsoft 365',
        };
    }
}
