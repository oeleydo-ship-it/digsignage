<?php

namespace App\Actions\Signage;

use App\Models\Team;

class SaveMonitoringSettings
{
    /**
     * Persist team-specific offline detection thresholds.
     */
    public function handle(Team $team, int $healthySeconds, int $warningSeconds): void
    {
        $settings = is_array($team->settings) ? $team->settings : [];
        $settings['monitoring'] = [
            'healthy_seconds' => max(30, $healthySeconds),
            'warning_seconds' => max(max(30, $healthySeconds) + 30, $warningSeconds),
        ];

        $team->forceFill(['settings' => $settings])->save();

        app(EvaluateScreenHealth::class)->handle($team->fresh());
    }
}
