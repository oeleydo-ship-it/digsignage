<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\PlaylistRevision;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistRevision>
 */
class PlaylistRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'playlist_id' => Playlist::factory(),
            'version' => 1,
            'items' => [],
            'loop' => true,
        ];
    }
}
