<?php

namespace Database\Factories;

use App\Enums\SignageAlert;
use App\Models\NotificationChannelPreference;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannelPreference>
 */
class NotificationChannelPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'event' => SignageAlert::ScreenOffline,
            'email' => true,
            'in_app' => true,
            'webhook' => false,
            'slack' => false,
        ];
    }
}
