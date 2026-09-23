<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueService;
use App\Models\User;

class QueueServicePolicy
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
    public function view(User $user, QueueService $queueService): bool
    {
        return $user->currentTeam?->id === $queueService->team_id
            && $user->hasTeamPermission($queueService->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueueServices);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QueueService $queueService): bool
    {
        return $user->currentTeam?->id === $queueService->team_id
            && $user->hasTeamPermission($queueService->team, TeamPermission::ManageQueueServices);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QueueService $queueService): bool
    {
        return $user->currentTeam?->id === $queueService->team_id
            && $user->hasTeamPermission($queueService->team, TeamPermission::ManageQueueServices);
    }
}
