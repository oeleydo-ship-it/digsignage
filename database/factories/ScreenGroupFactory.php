<?php

namespace Database\Factories;

use App\Models\ScreenGroup;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenGroup>
 */
class ScreenGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->company().' Group',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
