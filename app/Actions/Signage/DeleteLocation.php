<?php

namespace App\Actions\Signage;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

class DeleteLocation
{
    /**
     * Delete a location that has no children or screens.
     */
    public function handle(Location $location): void
    {
        if ($location->children()->exists() || $location->screens()->exists()) {
            throw ValidationException::withMessages([
                'location' => __('Move or delete child locations and screens before deleting this location.'),
            ]);
        }

        $location->delete();
    }
}
