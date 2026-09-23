<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\QueueAppointment;
use App\Models\User;

class QueueAppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewQueue);
    }

    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageQueueAppointments);
    }

    public function update(User $user, QueueAppointment $appointment): bool
    {
        return $user->currentTeam?->id === $appointment->team_id
            && $user->hasTeamPermission($appointment->team, TeamPermission::ManageQueueAppointments);
    }

    public function delete(User $user, QueueAppointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
