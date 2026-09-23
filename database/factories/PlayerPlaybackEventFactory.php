<?php

namespace Database\Factories;

use App\Enums\PlaybackStatus;
use App\Models\PlayerPlaybackEvent;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerPlaybackEvent>
 */
class PlayerPlaybackEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started = now()->subMinutes(fake()->numberBetween(1, 240));

        return [
            'team_id' => Team::factory(),
            'screen_id' => Screen::factory(),
            'title' => fake()->words(3, true),
            'content_id' => 'item:'.fake()->numberBetween(1, 99),
            'item_key' => 'item:'.fake()->numberBetween(1, 99),
            'duration_ms' => 15000,
            'played_at' => $started,
            'started_at' => $started,
            'ended_at' => $started->addSeconds(15),
            'status' => PlaybackStatus::Completed,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PlayerPlaybackEvent $event) {
            $screen = Screen::query()->find($event->screen_id);

            if ($screen !== null) {
                $event->team_id = $screen->team_id;
                $event->location_id = $screen->location_id;
            }
        });
    }
}
