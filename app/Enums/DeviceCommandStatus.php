<?php

namespace App\Enums;

enum DeviceCommandStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Commands still waiting for the player.
     *
     * @return list<self>
     */
    public static function deliverable(): array
    {
        return [self::Pending, self::Sent];
    }
}
