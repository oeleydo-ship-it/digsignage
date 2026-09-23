<?php

namespace Database\Factories;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->bothify('Channel-###'),
            'description' => null,
            'type' => ChannelType::Playlist,
            'status' => ChannelStatus::Draft,
            'width' => 1920,
            'height' => 1080,
            'version' => 1,
        ];
    }

    /**
     * Indicate that the channel is a multi-zone layout.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChannelType::Advanced,
        ]);
    }

    /**
     * Indicate that the channel is a live stream.
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ChannelType::Live,
            'live_protocol' => 'hls',
            'live_url' => 'https://example.com/live/stream.m3u8',
        ]);
    }
}
