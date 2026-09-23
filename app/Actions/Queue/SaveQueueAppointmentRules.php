<?php

namespace App\Actions\Queue;

use App\Data\QueueAppointmentConfig;
use App\Models\QueueSetting;
use App\Models\Team;

class SaveQueueAppointmentRules
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes): QueueSetting
    {
        $settings = QueueSetting::resolveForTeam($team);
        $current = $settings->settings ?? [];
        $current['appointments'] = QueueAppointmentConfig::fromArray($attributes)->toArray();
        $settings->forceFill(['settings' => $current])->save();

        return $settings->refresh();
    }
}
