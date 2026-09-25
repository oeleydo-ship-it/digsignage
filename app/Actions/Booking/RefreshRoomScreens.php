<?php

namespace App\Actions\Booking;

use App\Events\PlayerManifestUpdated;
use App\Models\Screen;
use App\Support\PlayerManifestCache;
use Illuminate\Support\Facades\DB;

/**
 * Push booking changes to screens straight away.
 *
 * Room widgets are resolved into the player manifest, so a booking change
 * invalidates the team's cached manifests and nudges every paired screen to
 * refetch, the same way content edits reach players.
 */
class RefreshRoomScreens
{
    public function handle(int $teamId): void
    {
        $notify = function () use ($teamId): void {
            PlayerManifestCache::bumpTeam($teamId);

            Screen::query()
                ->where('team_id', $teamId)
                ->whereNotNull('device_uuid')
                ->where('device_uuid', '!=', '')
                ->pluck('device_uuid')
                ->each(fn (string $uuid) => event(new PlayerManifestUpdated($uuid)));
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($notify);

            return;
        }

        $notify();
    }
}
