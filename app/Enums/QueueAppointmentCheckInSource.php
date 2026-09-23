<?php

namespace App\Enums;

enum QueueAppointmentCheckInSource: string
{
    case Kiosk = 'kiosk';
    case Qr = 'qr';
    case Staff = 'staff';
    case Reference = 'reference';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
