<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyAuditAction;
use App\Models\Emergency;
use App\Models\EmergencyAudit;
use App\Models\User;

class RecordEmergencyAudit
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(
        Emergency $emergency,
        EmergencyAuditAction $action,
        ?User $user = null,
        ?int $screenId = null,
        ?array $payload = null,
    ): EmergencyAudit {
        return EmergencyAudit::query()->create([
            'team_id' => $emergency->team_id,
            'emergency_id' => $emergency->id,
            'user_id' => $user?->id,
            'screen_id' => $screenId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
