<?php

namespace App\Enums;

enum QueueTicketSource: string
{
    case Kiosk = 'kiosk';
    case Staff = 'staff';
    case Qr = 'qr';
    case Website = 'website';
    case Appointment = 'appointment';
    case Api = 'api';

    /**
     * Get the display label for the issuance source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Kiosk => 'Kiosk',
            self::Staff => 'Staff',
            self::Qr => 'QR',
            self::Website => 'Website',
            self::Appointment => 'Appointment',
            self::Api => 'API',
        };
    }
}
