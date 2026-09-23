<?php

namespace App\Enums;

enum QueueTicketEventType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Transferred = 'transferred';

    /**
     * Get the display label for the journey event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::StatusChanged => 'Status changed',
            self::Transferred => 'Transferred',
        };
    }
}
