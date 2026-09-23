<?php

namespace App\Actions\Queue;

use App\Data\QueueKioskBranding;
use App\Models\Location;
use App\Models\QueueKiosk;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SaveQueueKiosk
{
    /**
     * Create or update a team-scoped kiosk.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes, ?QueueKiosk $kiosk = null): QueueKiosk
    {
        $location = $this->location($team, $attributes['location_id'] ?? null);
        $branding = QueueKioskBranding::fromArray(
            is_array($attributes['branding'] ?? null) ? $attributes['branding'] : null,
        )->toArray();

        $kiosk ??= new QueueKiosk(['team_id' => $team->id]);

        $kiosk->fill([
            'location_id' => $location?->id,
            'name' => $attributes['name'],
            'branding' => $branding,
            'printer_enabled' => (bool) ($attributes['printer_enabled'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
        ]);

        $pin = $attributes['pin'] ?? null;

        if (is_string($pin) && trim($pin) !== '') {
            $kiosk->pin = Hash::make(trim($pin));
        }

        if (($attributes['clear_pin'] ?? false) === true) {
            $kiosk->pin = null;
        }

        $kiosk->save();

        return $kiosk->refresh()->load('location');
    }

    protected function location(Team $team, mixed $locationId): ?Location
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $location = Location::query()
            ->forTeam($team)
            ->whereKey($locationId)
            ->first();

        if ($location === null) {
            throw ValidationException::withMessages([
                'location_id' => __('The selected location is invalid.'),
            ]);
        }

        return $location;
    }
}
