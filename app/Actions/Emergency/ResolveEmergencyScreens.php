<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyTargetType;
use App\Models\Emergency;
use App\Models\Location;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Support\Collection;

class ResolveEmergencyScreens
{
    /**
     * Expand location descendants and explicit screens into a unique screen list.
     *
     * @return Collection<int, Screen>
     */
    public function handle(Team $team, Emergency $emergency): Collection
    {
        $screenIds = [];
        $locationIds = [];

        foreach ($emergency->targets as $target) {
            if ($target->target_type === EmergencyTargetType::Screen && $target->screen_id !== null) {
                $screenIds[] = $target->screen_id;
            }

            if ($target->target_type === EmergencyTargetType::Location && $target->location_id !== null) {
                $locationIds[] = $target->location_id;
            }
        }

        if ($locationIds !== []) {
            $locations = Location::query()
                ->forTeam($team)
                ->whereIn('id', array_values(array_unique($locationIds)))
                ->get();

            $expandedIds = $locations->pluck('id')->all();

            foreach ($locations as $location) {
                $descendants = Location::query()
                    ->forTeam($team)
                    ->where('path', 'like', $location->descendantPathPrefix().'%')
                    ->pluck('id')
                    ->all();

                $expandedIds = array_merge($expandedIds, $descendants);
            }

            $locationScreenIds = Screen::query()
                ->forTeam($team)
                ->whereIn('location_id', array_values(array_unique($expandedIds)))
                ->pluck('id')
                ->all();

            $screenIds = array_merge($screenIds, $locationScreenIds);
        }

        $screenIds = array_values(array_unique($screenIds));

        if ($screenIds === []) {
            return collect();
        }

        return Screen::query()
            ->forTeam($team)
            ->whereIn('id', $screenIds)
            ->orderBy('name')
            ->get();
    }
}
