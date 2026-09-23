<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueuePriority;
use App\Models\User;

class QueuePriorityPolicy
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
    public function view(User $user, QueuePriority $queuePriority): bool
    {
        return $user->currentTeam?->id === $queuePriority->team_id
            && $user->hasTeamPermission($queuePriority->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueueSettings);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QueuePriority $queuePriority): bool
    {
        return $user->currentTeam?->id === $queuePriority->team_id
            && $user->hasTeamPermission($queuePriority->team, TeamPermission::ManageQueueSettings);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QueuePriority $queuePriority): bool
    {
        return $user->currentTeam?->id === $queuePriority->team_id
            && $user->hasTeamPermission($queuePriority->team, TeamPermission::ManageQueueSettings);
    }
}
