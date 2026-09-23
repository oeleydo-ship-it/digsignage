<?php

namespace Database\Factories;

use App\Enums\EmergencySeverity;
use App\Enums\EmergencyStatus;
use App\Models\Emergency;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Emergency>
 */
class EmergencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'instructions' => fake()->optional()->sentence(),
            'background' => '#b91c1c',
            'severity' => EmergencySeverity::Emergency,
            'status' => EmergencyStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => EmergencyStatus::Active,
            'started_at' => now(),
            'starts_at' => now()->subMinute(),
        ]);
    }
}
