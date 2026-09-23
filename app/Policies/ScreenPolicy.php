<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Screen;
use App\Models\User;

class ScreenPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewScreens);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Screen $screen): bool
    {
        return $user->currentTeam?->id === $screen->team_id
            && $user->hasTeamPermission($screen->team, TeamPermission::ViewScreens);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateScreen);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Screen $screen): bool
    {
        return $user->currentTeam?->id === $screen->team_id
            && $user->hasTeamPermission($screen->team, TeamPermission::UpdateScreen);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Screen $screen): bool
    {
        return $user->currentTeam?->id === $screen->team_id
            && $user->hasTeamPermission($screen->team, TeamPermission::DeleteScreen);
    }

    /**
     * Determine whether the user can apply bulk updates.
     */
    public function bulkUpdate(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::UpdateScreen);
    }

    /**
     * Determine whether the user can pair a player to a screen.
     */
    public function pair(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::PairScreen);
    }

    /**
     * Determine whether the user can send remote player commands.
     */
    public function command(User $user, Screen $screen): bool
    {
        return $this->update($user, $screen);
    }

    /**
     * Determine whether the user can rotate a paired player's credentials.
     */
    public function rotateCredentials(User $user, Screen $screen): bool
    {
        return $user->currentTeam?->id === $screen->team_id
            && $user->hasTeamPermission($screen->team, TeamPermission::PairScreen);
    }
}
