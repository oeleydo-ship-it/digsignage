<?php

namespace Database\Factories;

use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screen>
 */
class ScreenFactory extends Factory
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
            'location_id' => null,
            'name' => fake()->unique()->company().' Screen',
            'description' => fake()->optional()->sentence(),
            'orientation' => ScreenOrientation::Landscape,
            'resolution_width' => 1920,
            'resolution_height' => 1080,
            'timezone' => null,
            'status' => ScreenStatus::Offline,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the screen is paired to a device.
     */
    public function paired(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt('device-token'),
            'device_token_hash' => hash('sha256', 'device-token'),
        ]);
    }

    /**
     * Indicate that the screen is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScreenStatus::Disabled,
        ]);
    }
}
