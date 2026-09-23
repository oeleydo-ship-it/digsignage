<?php

namespace App\Enums;

enum ScheduleContentType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';

    /**
     * Get the display label for the content type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Channel => 'Channel',
            self::Playlist => 'Playlist',
        };
    }
}
