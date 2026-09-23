<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Design;
use App\Models\User;

class DesignPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewDesigns);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Design $design): bool
    {
        return $user->currentTeam?->id === $design->team_id
            && $user->hasTeamPermission($design->team, TeamPermission::ViewDesigns);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateDesign);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Design $design): bool
    {
        return $user->currentTeam?->id === $design->team_id
            && $user->hasTeamPermission($design->team, TeamPermission::UpdateDesign);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Design $design): bool
    {
        return $user->currentTeam?->id === $design->team_id
            && $user->hasTeamPermission($design->team, TeamPermission::DeleteDesign);
    }
}
