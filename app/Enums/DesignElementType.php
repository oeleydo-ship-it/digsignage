<?php

namespace App\Enums;

enum DesignElementType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Shape = 'shape';
    case Button = 'button';
    case Logo = 'logo';
    case Clock = 'clock';
    case Date = 'date';
    case Weather = 'weather';
    case Ticker = 'ticker';
    case Rss = 'rss';
    case WebPage = 'web_page';
    case QrCode = 'qr_code';
    case Iframe = 'iframe';
    case LiveStream = 'live_stream';
    case Calendar = 'calendar';
    case Table = 'table';
    case Chart = 'chart';
    case Icon = 'icon';

    /**
     * Get the display label for the element type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WebPage => 'Web page',
            self::QrCode => 'QR code',
            self::LiveStream => 'Live stream',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
