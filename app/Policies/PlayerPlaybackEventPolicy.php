<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\PlayerPlaybackEvent;
use App\Models\User;

class PlayerPlaybackEventPolicy
{
    /**
     * Determine whether the user can view proof-of-play reports.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewScreens);
    }

    /**
     * Determine whether the user can view a single playback event.
     */
    public function view(User $user, PlayerPlaybackEvent $event): bool
    {
        return $user->currentTeam?->id === $event->team_id
            && $user->hasTeamPermission($event->team, TeamPermission::ViewScreens);
    }
}
