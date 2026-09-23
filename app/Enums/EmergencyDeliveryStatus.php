<?php

namespace App\Enums;

enum EmergencyDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';
    case Unreachable = 'unreachable';

    /**
     * Get the display label for the delivery status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
