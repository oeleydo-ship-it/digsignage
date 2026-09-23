<?php

namespace App\Actions\Player;

use App\Enums\DeviceCommandStatus;
use App\Models\DeviceCommand;
use App\Models\Screen;

class ExpireDeviceCommands
{
    /**
     * Mark undelivered commands past their TTL as expired.
     */
    public function handle(?Screen $screen = null): int
    {
        $query = DeviceCommand::query()
            ->whereIn('status', [
                DeviceCommandStatus::Pending->value,
                DeviceCommandStatus::Sent->value,
                DeviceCommandStatus::Acknowledged->value,
            ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        if ($screen !== null) {
            $query->where('screen_id', $screen->id);
        }

        return $query->update([
            'status' => DeviceCommandStatus::Expired->value,
        ]);
    }
}
