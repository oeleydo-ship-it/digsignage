<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
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
            'parent_id' => null,
            'type' => LocationType::City,
            'name' => fake()->unique()->city(),
            'description' => fake()->optional()->sentence(),
            'address' => fake()->optional()->address(),
            'timezone' => 'UTC',
            'path' => '/',
            'depth' => 0,
            'tags' => [],
            'metadata' => [],
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Location $location) {
            $location->rebuildPath();
        });
    }
}
