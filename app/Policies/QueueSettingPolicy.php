<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueSetting;
use App\Models\User;

class QueueSettingPolicy
{
    /**
     * Determine whether the user can view queue pages for the current team.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can view the team's queue settings.
     */
    public function view(User $user, QueueSetting $queueSetting): bool
    {
        return $user->currentTeam?->id === $queueSetting->team_id
            && $user->hasTeamPermission($queueSetting->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can update queue settings.
     */
    public function update(User $user, QueueSetting $queueSetting): bool
    {
        return $user->currentTeam?->id === $queueSetting->team_id
            && $user->hasTeamPermission($queueSetting->team, TeamPermission::ManageQueueSettings);
    }
}
