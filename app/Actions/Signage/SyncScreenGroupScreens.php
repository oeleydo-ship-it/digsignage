<?php

namespace App\Actions\Signage;

use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Support\PlayerManifestCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncScreenGroupScreens
{
    /**
     * Replace the screens assigned to a group.
     *
     * @param  list<int>  $screenIds
     */
    public function handle(Team $team, ScreenGroup $group, array $screenIds): ScreenGroup
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

        DB::transaction(function () use ($group, $screens) {
            $group->screens()->sync($screens->pluck('id')->all());
        });

        // Pivot syncs fire no model events; group membership changes what
        // schedule-targeted manifests resolve to.
        PlayerManifestCache::bumpTeam($group->team_id);

        return $group->refresh();
    }
}
