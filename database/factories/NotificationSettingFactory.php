<?php

namespace Database\Factories;

use App\Models\NotificationSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'webhook_url' => null,
            'slack_webhook_url' => null,
            'min_player_version' => null,
        ];
    }
}
