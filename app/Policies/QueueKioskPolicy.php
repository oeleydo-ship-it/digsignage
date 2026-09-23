<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueKiosk;
use App\Models\User;

class QueueKioskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QueueKiosk $queueKiosk): bool
    {
        return $user->currentTeam?->id === $queueKiosk->team_id
            && $user->hasTeamPermission($queueKiosk->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueueKiosks);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QueueKiosk $queueKiosk): bool
    {
        return $user->currentTeam?->id === $queueKiosk->team_id
            && $user->hasTeamPermission($queueKiosk->team, TeamPermission::ManageQueueKiosks);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QueueKiosk $queueKiosk): bool
    {
        return $user->currentTeam?->id === $queueKiosk->team_id
            && $user->hasTeamPermission($queueKiosk->team, TeamPermission::ManageQueueKiosks);
    }
}
