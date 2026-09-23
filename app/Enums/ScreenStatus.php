<?php

namespace App\Enums;

enum ScreenStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Warning = 'warning';
    case Updating = 'updating';
    case Disabled = 'disabled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
