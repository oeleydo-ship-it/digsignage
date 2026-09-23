<?php

namespace Database\Factories;

use App\Enums\QueueCounterStatus;
use App\Models\QueueCounter;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueCounter>
 */
class QueueCounterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'location_id' => null,
            'name' => 'Counter '.fake()->unique()->numerify('##'),
            'code' => strtoupper(fake()->unique()->bothify('CTR###')),
            'status' => QueueCounterStatus::Open,
            'assigned_user_id' => null,
        ];
    }

    /**
     * Closed desk.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueCounterStatus::Closed,
        ]);
    }
}
