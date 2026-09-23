<?php

namespace App\Enums;

enum PlaybackStatus: string
{
    case Completed = 'completed';
    case Interrupted = 'interrupted';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Interrupted => 'Interrupted',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed',
        };
    }
}
