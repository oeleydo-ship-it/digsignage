<?php

namespace App\Actions\Platform;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAudit;
use App\Models\User;
use Illuminate\Support\Str;

class RecordPlatformAudit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(
        PlatformAuditAction $action,
        ?User $actor,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?array $before = null,
        ?array $after = null,
    ): PlatformAudit {
        $request = request();
        $agent = $request->userAgent();

        return PlatformAudit::query()->create([
            'actor_id' => $actor?->id,
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
