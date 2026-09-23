<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can browse organization audit logs.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewAuditLogs);
    }

    /**
     * Determine whether the user can view a single audit row.
     */
    public function view(User $user, AuditLog $log): bool
    {
        return $user->currentTeam?->id === $log->team_id
            && $user->hasTeamPermission($log->team, TeamPermission::ViewAuditLogs);
    }
}
