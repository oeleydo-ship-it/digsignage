<?php

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

class RecordOrganizationAudit
{
    /**
     * Persist a searchable organization audit event.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        Team $team,
        AuditAction $action,
        ?User $actor,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $request = request();
        $agent = $request->userAgent();

        return AuditLog::query()->create([
            'team_id' => $team->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => is_numeric($resourceId) ? (int) $resourceId : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => is_string($agent) && $agent !== '' ? Str::limit($agent, 512, '') : null,
            'created_at' => now(),
        ]);
    }
}
