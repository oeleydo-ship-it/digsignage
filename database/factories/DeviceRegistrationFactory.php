<?php

namespace Database\Factories;

use App\Models\DeviceRegistration;
use App\Support\RegistrationCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceRegistration>
 */
class DeviceRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code_hash' => RegistrationCode::hash(RegistrationCode::generate()),
            'expires_at' => now()->addMinutes(15),
            'player_ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the registration has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
