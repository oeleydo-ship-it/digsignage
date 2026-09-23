<?php

namespace App\Actions\Queue;

use App\Data\QueueVoiceConfig;
use App\Models\QueueSetting;
use App\Models\Team;

class SaveQueueVoiceSettings
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes): QueueSetting
    {
        $settings = QueueSetting::resolveForTeam($team);
        $current = $settings->settings ?? [];
        $current['voice'] = QueueVoiceConfig::fromArray($attributes)->toArray();
        $settings->forceFill(['settings' => $current])->save();

        return $settings->refresh();
    }
}
