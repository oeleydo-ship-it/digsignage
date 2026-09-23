<?php

namespace Database\Factories;

use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Models\Channel;
use App\Models\Schedule;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'channel_id' => Channel::factory(),
            'name' => fake()->unique()->words(2, true),
            'content_type' => ScheduleContentType::Channel,
            'timezone' => 'UTC',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'start_time' => '06:00:00',
            'end_time' => '11:00:00',
            'recurrence' => ScheduleRecurrence::Daily,
            'weekdays' => null,
            'priority' => 100,
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate a lunch daypart.
     */
    public function lunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Lunch Menu',
            'start_time' => '11:00:00',
            'end_time' => '16:00:00',
            'priority' => 100,
        ]);
    }

    /**
     * Indicate a dinner daypart.
     */
    public function dinner(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Dinner Menu',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'priority' => 100,
        ]);
    }
}
