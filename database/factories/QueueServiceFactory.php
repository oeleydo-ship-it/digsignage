<?php

namespace Database\Factories;

use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Models\QueueService;
use App\Models\Team;
use App\Support\QueueOpeningHours;
use App\Support\QueueTicketNumbering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueService>
 */
class QueueServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reset = QueueNumberingReset::Daily;

        return [
            'team_id' => Team::factory(),
            'location_id' => null,
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('SVC###')),
            'ticket_prefix' => strtoupper(fake()->unique()->bothify('??')),
            'description' => fake()->optional()->sentence(),
            'opening_hours' => QueueOpeningHours::defaults(),
            'average_service_duration_seconds' => 300,
            'max_queue_capacity' => null,
            'numbering_reset' => $reset,
            'next_sequence' => 1,
            'last_issued' => null,
            'sequence_period' => QueueTicketNumbering::periodKey($reset, now()),
            'priority_rules' => [],
            'default_priority' => 0,
            'queue_strategy' => QueueStrategy::Fifo,
            'starvation' => null,
            'display_color' => '#2563eb',
            'is_active' => true,
        ];
    }

    /**
     * Inactive service.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
