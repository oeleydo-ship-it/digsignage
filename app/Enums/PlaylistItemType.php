<?php

namespace App\Enums;

enum PlaylistItemType: string
{
    case Media = 'media';
    case Design = 'design';
    case Template = 'template';
    case WebPage = 'web_page';
    case LiveStream = 'live_stream';
    case Widget = 'widget';

    /**
     * Get the display label for the item type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WebPage => 'Web page',
            self::LiveStream => 'Live stream',
            default => ucfirst($this->value),
        };
    }
}
