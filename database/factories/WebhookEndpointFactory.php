<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'url' => 'https://hooks.example.test/signage',
            'secret' => Str::random(40),
            'events' => array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::cases()),
            'is_active' => true,
        ];
    }
}
