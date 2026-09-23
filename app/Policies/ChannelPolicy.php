<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewChannels);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Channel $channel): bool
    {
        return $user->currentTeam?->id === $channel->team_id
            && $user->hasTeamPermission($channel->team, TeamPermission::ViewChannels);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateChannel);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Channel $channel): bool
    {
        return $user->currentTeam?->id === $channel->team_id
            && $user->hasTeamPermission($channel->team, TeamPermission::UpdateChannel);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Channel $channel): bool
    {
        return $user->currentTeam?->id === $channel->team_id
            && $user->hasTeamPermission($channel->team, TeamPermission::DeleteChannel);
    }
}
