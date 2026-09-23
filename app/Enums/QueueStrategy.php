<?php

namespace App\Enums;

enum QueueStrategy: string
{
    case Fifo = 'fifo';
    case Priority = 'priority';

    /**
     * Get the display label for the dispatch strategy.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO',
            self::Priority => 'Priority',
        };
    }
}
