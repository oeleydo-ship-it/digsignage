<?php

namespace App\Actions\Queue;

use App\Data\QueueStarvationConfig;
use App\Models\QueueSetting;
use App\Models\Team;

class SaveQueueStarvationSettings
{
    /**
     * Persist team-wide starvation defaults on the queue settings JSON.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes): QueueSetting
    {
        $settings = QueueSetting::resolveForTeam($team);
        $current = $settings->settings ?? [];
        $starvation = QueueStarvationConfig::fromArray($attributes);

        $current['starvation'] = $starvation->toArray();
        $settings->forceFill(['settings' => $current])->save();

        return $settings->refresh();
    }
}
