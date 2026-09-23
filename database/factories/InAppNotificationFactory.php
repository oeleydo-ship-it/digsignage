<?php

namespace Database\Factories;

use App\Enums\SignageAlert;
use App\Models\InAppNotification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InAppNotification>
 */
class InAppNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'event' => SignageAlert::ScreenOffline,
            'title' => 'Screen offline',
            'body' => 'A screen stopped sending heartbeats.',
            'data' => [],
            'read_at' => null,
        ];
    }
}
