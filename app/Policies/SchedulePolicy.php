<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewSchedules);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->currentTeam?->id === $schedule->team_id
            && $user->hasTeamPermission($schedule->team, TeamPermission::ViewSchedules);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateSchedule);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        return $user->currentTeam?->id === $schedule->team_id
            && $user->hasTeamPermission($schedule->team, TeamPermission::UpdateSchedule);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->currentTeam?->id === $schedule->team_id
            && $user->hasTeamPermission($schedule->team, TeamPermission::DeleteSchedule);
    }
}
