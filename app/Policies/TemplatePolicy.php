<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewTemplates);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Template $template): bool
    {
        $team = $user->currentTeam;

        if ($team === null || ! $user->hasTeamPermission($team, TeamPermission::ViewTemplates)) {
            return false;
        }

        if ($template->isPlatform()) {
            return $user->is_platform_admin === true || $template->status === TemplateStatus::Published;
        }

        return $template->team_id === $team->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateTemplate);
    }

    /**
     * Determine whether the user can create platform templates.
     */
    public function createPlatform(User $user): bool
    {
        return $user->is_platform_admin === true && $this->create($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Template $template): bool
    {
        $team = $user->currentTeam;

        if ($team === null || ! $user->hasTeamPermission($team, TeamPermission::UpdateTemplate)) {
            return false;
        }

        if ($template->isPlatform()) {
            return $user->is_platform_admin === true;
        }

        return $template->team_id === $team->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Template $template): bool
    {
        $team = $user->currentTeam;

        if ($team === null || ! $user->hasTeamPermission($team, TeamPermission::DeleteTemplate)) {
            return false;
        }

        if ($template->isPlatform()) {
            return $user->is_platform_admin === true;
        }

        return $template->team_id === $team->id;
    }
}
