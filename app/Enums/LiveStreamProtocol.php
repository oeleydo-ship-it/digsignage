<?php

namespace App\Enums;

enum LiveStreamProtocol: string
{
    case Hls = 'hls';
    case Dash = 'dash';
    case Rtsp = 'rtsp';
    case Iptv = 'iptv';

    /**
     * Get the display label for the protocol.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hls => 'HLS',
            self::Dash => 'DASH',
            self::Rtsp => 'RTSP',
            self::Iptv => 'IPTV',
        };
    }
}
