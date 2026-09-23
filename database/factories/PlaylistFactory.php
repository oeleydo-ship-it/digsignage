<?php

namespace Database\Factories;

use App\Enums\PlaylistStatus;
use App\Models\Playlist;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->bothify('Playlist-###'),
            'description' => null,
            'status' => PlaylistStatus::Draft,
            'loop' => true,
            'version' => 1,
            'duration_seconds' => 0,
        ];
    }

    /**
     * Indicate that the playlist is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PlaylistStatus::Published,
            'published_at' => now(),
        ]);
    }
}
