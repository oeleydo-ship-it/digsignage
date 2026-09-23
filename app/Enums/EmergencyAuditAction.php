<?php

namespace App\Enums;

enum EmergencyAuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Started = 'started';
    case Scheduled = 'scheduled';
    case Stopped = 'stopped';
    case Expired = 'expired';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';

    /**
     * Get the display label for the audit action.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
