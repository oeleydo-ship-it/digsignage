<?php

namespace App\Enums;

enum QueueCounterStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Busy = 'busy';
    case Paused = 'paused';

    /**
     * Get the display label for the counter status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Busy => 'Busy',
            self::Paused => 'Paused',
        };
    }

    /**
     * Whether staff may request the next ticket from this desk.
     */
    public function allowsCallNext(): bool
    {
        return $this === self::Open || $this === self::Busy;
    }
}
