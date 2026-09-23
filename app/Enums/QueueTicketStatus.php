<?php

namespace App\Enums;

enum QueueTicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case OnHold = 'on_hold';
    case Transferred = 'transferred';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the ticket status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Called => 'Called',
            self::Serving => 'Serving',
            self::OnHold => 'On hold',
            self::Transferred => 'Transferred',
            self::Completed => 'Completed',
            self::NoShow => 'No show',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tickets still occupying a place in line.
     */
    public function isWaiting(): bool
    {
        return $this === self::Waiting;
    }

    /**
     * Finished tickets that must not re-enter a queue.
     */
    public function isClosed(): bool
    {
        return $this === self::Completed
            || $this === self::NoShow
            || $this === self::Cancelled;
    }
}
