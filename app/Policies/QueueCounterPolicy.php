<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueCounter;
use App\Models\User;

class QueueCounterPolicy
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
    public function view(User $user, QueueCounter $queueCounter): bool
    {
        return $user->currentTeam?->id === $queueCounter->team_id
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueueCounters);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QueueCounter $queueCounter): bool
    {
        return $user->currentTeam?->id === $queueCounter->team_id
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::ManageQueueCounters);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QueueCounter $queueCounter): bool
    {
        return $user->currentTeam?->id === $queueCounter->team_id
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::ManageQueueCounters);
    }

    /**
     * Assigned staff or queue managers may operate the desk.
     */
    public function operate(User $user, QueueCounter $queueCounter): bool
    {
        if ($user->currentTeam?->id !== $queueCounter->team_id) {
            return false;
        }

        $team = $queueCounter->team;

        return $this->canUseDesk($user, $queueCounter)
            && ($user->hasTeamPermission($team, TeamPermission::CallQueue)
                || $user->hasTeamPermission($team, TeamPermission::TransferQueue)
                || $user->hasTeamPermission($team, TeamPermission::CompleteQueue));
    }

    public function call(User $user, QueueCounter $queueCounter): bool
    {
        return $this->canUseDesk($user, $queueCounter)
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::CallQueue);
    }

    public function transfer(User $user, QueueCounter $queueCounter): bool
    {
        return $this->canUseDesk($user, $queueCounter)
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::TransferQueue);
    }

    public function complete(User $user, QueueCounter $queueCounter): bool
    {
        return $this->canUseDesk($user, $queueCounter)
            && $user->hasTeamPermission($queueCounter->team, TeamPermission::CompleteQueue);
    }

    protected function canUseDesk(User $user, QueueCounter $queueCounter): bool
    {
        if ($user->currentTeam?->id !== $queueCounter->team_id) {
            return false;
        }

        if ($user->hasTeamPermission($queueCounter->team, TeamPermission::ManageQueueCounters)) {
            return true;
        }

        return $queueCounter->assigned_user_id === $user->id;
    }
}
