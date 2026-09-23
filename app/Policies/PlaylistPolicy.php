<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewPlaylists);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Playlist $playlist): bool
    {
        return $user->currentTeam?->id === $playlist->team_id
            && $user->hasTeamPermission($playlist->team, TeamPermission::ViewPlaylists);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreatePlaylist);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Playlist $playlist): bool
    {
        return $user->currentTeam?->id === $playlist->team_id
            && $user->hasTeamPermission($playlist->team, TeamPermission::UpdatePlaylist);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Playlist $playlist): bool
    {
        return $user->currentTeam?->id === $playlist->team_id
            && $user->hasTeamPermission($playlist->team, TeamPermission::DeletePlaylist);
    }
}
