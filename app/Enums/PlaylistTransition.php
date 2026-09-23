<?php

namespace App\Enums;

enum PlaylistTransition: string
{
    case None = 'none';
    case Cut = 'cut';
    case Fade = 'fade';
    case SlideLeft = 'slide_left';
    case SlideRight = 'slide_right';

    /**
     * Get the display label for the transition.
     */
    public function label(): string
    {
        return match ($this) {
            self::SlideLeft => 'Slide left',
            self::SlideRight => 'Slide right',
            default => ucfirst($this->value),
        };
    }
}
