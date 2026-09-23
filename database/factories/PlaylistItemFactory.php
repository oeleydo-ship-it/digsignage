<?php

namespace Database\Factories;

use App\Enums\PlaylistItemType;
use App\Enums\PlaylistTransition;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistItem>
 */
class PlaylistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $playlist = Playlist::factory();

        return [
            'team_id' => Team::factory(),
            'playlist_id' => $playlist,
            'type' => PlaylistItemType::WebPage,
            'title' => 'Company News',
            'duration_seconds' => 20,
            'transition' => PlaylistTransition::Fade,
            'transition_ms' => 400,
            'enabled' => true,
            'position' => 1,
            'url' => 'https://example.com/news',
        ];
    }
}
