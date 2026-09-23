<?php

namespace App\Support;

use App\Enums\ScheduleTargetType;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;

final class ScheduleAudience
{
    /**
     * Screen IDs reached by the given target payloads.
     *
     * @param  list<array<string, mixed>>  $targets
     * @return list<int>
     */
    public static function screenIdsForTargets(Team $team, array $targets): array
    {
        $ids = [];

        foreach ($targets as $target) {
            $type = $target['target_type'] ?? null;

            if ($type === ScheduleTargetType::Screen->value || $type === ScheduleTargetType::Screen) {
                $screenId = (int) ($target['screen_id'] ?? 0);

                if ($screenId > 0) {
                    $ids[] = $screenId;
                }

                continue;
            }

            if ($type === ScheduleTargetType::ScreenGroup->value || $type === ScheduleTargetType::ScreenGroup) {
                $groupId = (int) ($target['screen_group_id'] ?? 0);

                if ($groupId > 0) {
                    $groupIds = ScreenGroup::query()
                        ->forTeam($team)
                        ->whereKey($groupId)
                        ->first()
                        ?->screens()
                        ->pluck('screens.id')
                        ->all();

                    if (is_array($groupIds)) {
                        foreach ($groupIds as $id) {
                            $ids[] = (int) $id;
                        }
                    }
                }

                continue;
            }

            if ($type === ScheduleTargetType::Location->value || $type === ScheduleTargetType::Location) {
                $locationId = (int) ($target['location_id'] ?? 0);
                $location = $locationId > 0
                    ? Location::query()->forTeam($team)->whereKey($locationId)->first()
                    : null;

                if ($location !== null) {
                    $locationIds = Location::query()
                        ->forTeam($team)
                        ->where(function ($query) use ($location): void {
                            $query->whereKey($location->id)
                                ->orWhere('path', 'like', $location->descendantPathPrefix().'%');
                        })
                        ->pluck('id')
                        ->all();

                    $screenIds = Screen::query()
                        ->forTeam($team)
                        ->whereIn('location_id', $locationIds)
                        ->pluck('id')
                        ->all();

                    foreach ($screenIds as $id) {
                        $ids[] = (int) $id;
                    }
                }
            }
        }

        $unique = array_values(array_unique($ids));
        sort($unique);

        return $unique;
    }

    /**
     * Whether the schedule's targets include this screen.
     */
    public static function includesScreen(Schedule $schedule, Screen $screen): bool
    {
        $schedule->loadMissing(['targets.screenGroup.screens', 'targets.location']);

        foreach ($schedule->targets as $target) {
            if ($target->target_type === ScheduleTargetType::Screen && $target->screen_id === $screen->id) {
                return true;
            }

            if ($target->target_type === ScheduleTargetType::ScreenGroup) {
                $memberIds = $target->screenGroup?->screens->pluck('id')->all() ?? [];

                if (in_array($screen->id, $memberIds, true)) {
                    return true;
                }
            }

            if ($target->target_type === ScheduleTargetType::Location && $target->location !== null) {
                $location = $target->location;

                if ($screen->location_id === $location->id) {
                    return true;
                }

                $screenLocation = $screen->location;

                if ($screenLocation !== null && $location->isAncestorOf($screenLocation)) {
                    return true;
                }
            }
        }

        return false;
    }
}
