<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case HtmlPackage = 'html_package';
    case Url = 'url';
    case LiveStream = 'live_stream';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::HtmlPackage => 'HTML package',
            self::LiveStream => 'Live stream',
            self::Url => 'External URL',
            default => ucfirst($this->value),
        };
    }

    /**
     * Determine if this type is stored as an uploaded file.
     */
    public function storesFile(): bool
    {
        return in_array($this, [
            self::Image,
            self::Video,
            self::Audio,
            self::Pdf,
            self::HtmlPackage,
        ], true);
    }
}
