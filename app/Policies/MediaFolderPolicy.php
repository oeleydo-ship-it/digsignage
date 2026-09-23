<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
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
    public function update(User $user, MediaFolder $mediaFolder): bool
    {
        return $user->currentTeam?->id === $mediaFolder->team_id
            && $user->hasTeamPermission($mediaFolder->team, TeamPermission::UpdateMedia);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MediaFolder $mediaFolder): bool
    {
        return $user->currentTeam?->id === $mediaFolder->team_id
            && $user->hasTeamPermission($mediaFolder->team, TeamPermission::DeleteMedia);
    }
}
