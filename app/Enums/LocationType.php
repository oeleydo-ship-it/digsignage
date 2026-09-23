<?php

namespace App\Enums;

enum LocationType: string
{
    case Country = 'country';
    case City = 'city';
    case Building = 'building';
    case Floor = 'floor';
    case Area = 'area';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
