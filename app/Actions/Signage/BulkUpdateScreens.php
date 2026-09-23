<?php

namespace App\Actions\Signage;

use App\Enums\ScreenStatus;
use App\Models\Location;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Support\PlayerManifestCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkUpdateScreens
{
    /**
     * Apply a bulk action to screens in the current team.
     *
     * @param  list<int>  $screenIds
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $screenIds, string $action, array $attributes = []): int
    {
        $screens = Screen::query()
            ->forTeam($team)
            ->whereIn('id', $screenIds)
            ->get();

        if ($screens->count() !== count(array_unique($screenIds))) {
            throw ValidationException::withMessages([
                'screen_ids' => __('One or more screens could not be found in this organization.'),
            ]);
        }

        return DB::transaction(function () use ($team, $screens, $action, $attributes) {
            return match ($action) {
                'disable' => $this->disable($screens),
                'assign_location' => $this->assignLocation($team, $screens, $attributes['location_id'] ?? null),
                'assign_group' => $this->assignGroup($team, $screens, $attributes['screen_group_id'] ?? null),
                default => throw ValidationException::withMessages([
                    'action' => __('The selected bulk action is invalid.'),
                ]),
            };
        });
    }

    /**
     * @param  Collection<int, Screen>  $screens
     */
    protected function disable($screens): int
    {
        $screens->each(function (Screen $screen) {
            $screen->update(['status' => ScreenStatus::Disabled]);
        });

        return $screens->count();
    }

    /**
     * @param  Collection<int, Screen>  $screens
     */
    protected function assignLocation(Team $team, $screens, mixed $locationId): int
    {
        $location = null;

        if ($locationId !== null && $locationId !== '') {
            $location = Location::query()->forTeam($team)->whereKey($locationId)->first();

            if (! $location) {
                throw ValidationException::withMessages([
                    'location_id' => __('The selected location is invalid.'),
                ]);
            }
        }

        $screens->each(function (Screen $screen) use ($location) {
            $screen->update(['location_id' => $location?->id]);
        });

        return $screens->count();
    }

    /**
     * @param  Collection<int, Screen>  $screens
     */
    protected function assignGroup(Team $team, $screens, mixed $groupId): int
    {
        $group = ScreenGroup::query()->forTeam($team)->whereKey($groupId)->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'screen_group_id' => __('The selected group is invalid.'),
            ]);
        }

        $group->screens()->syncWithoutDetaching($screens->pluck('id')->all());

        // Pivot writes fire no model events; bump manifests explicitly.
        PlayerManifestCache::bumpTeam($group->team_id);

        return $screens->count();
    }
}
