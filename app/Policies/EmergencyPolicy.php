<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Emergency;
use App\Models\User;

class EmergencyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewEmergencies);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Emergency $emergency): bool
    {
        return $user->currentTeam?->id === $emergency->team_id
            && $user->hasTeamPermission($emergency->team, TeamPermission::ViewEmergencies);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateEmergency);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Emergency $emergency): bool
    {
        return $user->currentTeam?->id === $emergency->team_id
            && $user->hasTeamPermission($emergency->team, TeamPermission::UpdateEmergency);
    }

    /**
     * Determine whether the user can start the broadcast.
     */
    public function start(User $user, Emergency $emergency): bool
    {
        return $user->currentTeam?->id === $emergency->team_id
            && $user->hasTeamPermission($emergency->team, TeamPermission::StartEmergency);
    }

    /**
     * Determine whether the user can stop the broadcast.
     */
    public function stop(User $user, Emergency $emergency): bool
    {
        return $user->currentTeam?->id === $emergency->team_id
            && $user->hasTeamPermission($emergency->team, TeamPermission::StopEmergency);
    }
}
