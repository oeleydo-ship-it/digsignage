<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyStatus;
use App\Models\Emergency;

class ActivateScheduledEmergencies
{
    public function __construct(protected StartEmergencyBroadcast $start) {}

    public function handle(): int
    {
        $due = Emergency::query()
            ->where('status', EmergencyStatus::Scheduled)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($due as $emergency) {
            $this->start->activateDue($emergency);
        }

        return $due->count();
    }
}
