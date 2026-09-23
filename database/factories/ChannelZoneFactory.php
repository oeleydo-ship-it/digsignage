<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelZone;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelZone>
 */
class ChannelZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'channel_id' => Channel::factory()->advanced(),
            'name' => 'Main',
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 100,
            'z_index' => 1,
            'position' => 1,
        ];
    }
}
