<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyStatus;
use App\Models\Emergency;

class ExpireEmergencies
{
    public function __construct(protected StopEmergencyBroadcast $stop) {}

    public function handle(): int
    {
        $due = Emergency::query()
            ->whereIn('status', [EmergencyStatus::Active, EmergencyStatus::Scheduled])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($due as $emergency) {
            $this->stop->handle($emergency, null, confirmed: true, expired: true);
        }

        return $due->count();
    }
}
