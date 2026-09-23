<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewMedia);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Media $media): bool
    {
        return $user->currentTeam?->id === $media->team_id
            && $user->hasTeamPermission($media->team, TeamPermission::ViewMedia);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateMedia);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Media $media): bool
    {
        return $user->currentTeam?->id === $media->team_id
            && $user->hasTeamPermission($media->team, TeamPermission::UpdateMedia);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->currentTeam?->id === $media->team_id
            && $user->hasTeamPermission($media->team, TeamPermission::DeleteMedia);
    }
}
