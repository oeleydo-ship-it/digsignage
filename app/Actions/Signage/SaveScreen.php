<?php

namespace App\Actions\Signage;

use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use App\Models\Location;
use App\Models\Screen;
use App\Models\Team;
use App\Support\TeamQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveScreen
{
    /**
     * Create or update a screen.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes, ?Screen $screen = null): Screen
    {
        return DB::transaction(function () use ($team, $attributes, $screen) {
            $creating = $screen === null || ! $screen->exists;

            if ($creating) {
                app(TeamQuota::class)->assertCanAddScreen($team);
            }

            $location = $this->location($team, $attributes['location_id'] ?? null);

            $screen ??= new Screen([
                'team_id' => $team->id,
                'status' => ScreenStatus::Offline,
            ]);

            $screen->fill([
                'location_id' => $location?->id,
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'orientation' => $attributes['orientation'] ?? ScreenOrientation::Landscape,
                'resolution_width' => $attributes['resolution_width'] ?? null,
                'resolution_height' => $attributes['resolution_height'] ?? null,
                'timezone' => $attributes['timezone'] ?? null,
            ]);

            if (array_key_exists('status', $attributes) && $attributes['status'] !== null) {
                $screen->status = ScreenStatus::from($attributes['status']);
            }

            $screen->save();

            return $screen->refresh();
        });
    }

    /**
     * Resolve a location in the same team.
     */
    protected function location(Team $team, mixed $locationId): ?Location
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $location = Location::query()
            ->forTeam($team)
            ->whereKey($locationId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => __('The selected location is invalid.'),
            ]);
        }

        return $location;
    }
}
