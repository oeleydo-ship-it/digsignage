<?php

namespace App\Enums;

enum QueueNotificationEvent: string
{
    case TicketCreated = 'ticket_created';
    case FiveAhead = 'five_ahead';
    case ThreeAhead = 'three_ahead';
    case CustomerNext = 'customer_next';
    case TicketCalled = 'ticket_called';
    case TicketTransferred = 'ticket_transferred';
    case AppointmentApproaching = 'appointment_approaching';

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => 'Ticket created',
            self::FiveAhead => '5 customers ahead',
            self::ThreeAhead => '3 customers ahead',
            self::CustomerNext => 'Customer is next',
            self::TicketCalled => 'Ticket called',
            self::TicketTransferred => 'Ticket transferred',
            self::AppointmentApproaching => 'Appointment approaching',
        };
    }
}
