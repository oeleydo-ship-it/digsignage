<?php

namespace App\Enums;

enum ChannelType: string
{
    case Playlist = 'playlist';
    case Advanced = 'advanced';
    case Live = 'live';

    /**
     * Get the display label for the channel type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Playlist => 'Playlist',
            self::Advanced => 'Multi-zone',
            self::Live => 'Live',
        };
    }
}
