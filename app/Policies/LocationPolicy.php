<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewLocations);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Location $location): bool
    {
        return $user->currentTeam?->id === $location->team_id
            && $user->hasTeamPermission($location->team, TeamPermission::ViewLocations);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateLocation);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Location $location): bool
    {
        return $user->currentTeam?->id === $location->team_id
            && $user->hasTeamPermission($location->team, TeamPermission::UpdateLocation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Location $location): bool
    {
        return $user->currentTeam?->id === $location->team_id
            && $user->hasTeamPermission($location->team, TeamPermission::DeleteLocation);
    }
}
