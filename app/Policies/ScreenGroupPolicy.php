<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\ScreenGroup;
use App\Models\User;

class ScreenGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewScreenGroups);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ScreenGroup $screenGroup): bool
    {
        return $user->currentTeam?->id === $screenGroup->team_id
            && $user->hasTeamPermission($screenGroup->team, TeamPermission::ViewScreenGroups);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateScreenGroup);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ScreenGroup $screenGroup): bool
    {
        return $user->currentTeam?->id === $screenGroup->team_id
            && $user->hasTeamPermission($screenGroup->team, TeamPermission::UpdateScreenGroup);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ScreenGroup $screenGroup): bool
    {
        return $user->currentTeam?->id === $screenGroup->team_id
            && $user->hasTeamPermission($screenGroup->team, TeamPermission::DeleteScreenGroup);
    }
}
