<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\InAppNotification;
use App\Models\User;

class InAppNotificationPolicy
{
    /**
     * Determine whether the user can view the team inbox.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null && $user->belongsToTeam($user->currentTeam);
    }

    /**
     * Determine whether the user can view the notification.
     */
    public function view(User $user, InAppNotification $notification): bool
    {
        return $user->id === $notification->user_id
            && $user->currentTeam?->id === $notification->team_id;
    }

    /**
     * Determine whether the user can mark the notification as read.
     */
    public function update(User $user, InAppNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    /**
     * Determine whether the user can change delivery preferences.
     */
    public function manage(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }
}
