<?php

namespace App\Enums;

enum ScreenOrientation: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    /**
     * Get the display label for the orientation.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
