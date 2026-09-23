<?php

namespace Database\Factories;

use App\Data\QueueKioskBranding;
use App\Models\QueueKiosk;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueKiosk>
 */
class QueueKioskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'location_id' => null,
            'name' => 'Lobby kiosk '.fake()->unique()->numerify('##'),
            'branding' => QueueKioskBranding::defaults(),
            'printer_enabled' => false,
            'is_active' => true,
            'token' => QueueKiosk::generateToken(),
            'pin' => null,
        ];
    }

    /**
     * Inactive kiosk.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
