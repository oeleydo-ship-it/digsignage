<?php

namespace Database\Factories;

use App\Enums\AnalyticsEventType;
use App\Models\PlayerAnalyticsEvent;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerAnalyticsEvent>
 */
class PlayerAnalyticsEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'screen_id' => Screen::factory(),
            'type' => AnalyticsEventType::Heartbeat,
            'player_version' => 'web-1.0',
            'storage_warning' => false,
            'recorded_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PlayerAnalyticsEvent $event) {
            $screen = Screen::query()->find($event->screen_id);

            if ($screen !== null) {
                $event->team_id = $screen->team_id;
                $event->location_id = $screen->location_id;
            }
        });
    }
}
