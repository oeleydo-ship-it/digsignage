<?php

namespace App\Actions\Queue;

use App\Data\QueueAlertConfig;
use App\Models\QueueSetting;
use App\Models\Team;

class SaveQueueAlertSettings
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes): QueueSetting
    {
        $settings = QueueSetting::resolveForTeam($team);
        $current = $settings->settings ?? [];
        $current['alerts'] = QueueAlertConfig::fromArray($attributes)->toArray();

        $settings->forceFill(['settings' => $current])->save();

        return $settings->refresh();
    }
}
