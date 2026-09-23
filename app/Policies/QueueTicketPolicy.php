<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueTicket;
use App\Models\User;

class QueueTicketPolicy
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
    public function view(User $user, QueueTicket $queueTicket): bool
    {
        return $user->currentTeam?->id === $queueTicket->team_id
            && $user->hasTeamPermission($queueTicket->team, TeamPermission::ViewQueue);
    }

    /**
     * Determine whether the user can issue tickets.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueue);
    }

    /**
     * Determine whether the user can cancel a waiting ticket.
     */
    public function cancel(User $user, QueueTicket $queueTicket): bool
    {
        return $user->currentTeam?->id === $queueTicket->team_id
            && $user->hasTeamPermission($queueTicket->team, TeamPermission::CancelQueue);
    }
}
