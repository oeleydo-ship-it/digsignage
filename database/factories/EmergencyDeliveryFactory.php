<?php

namespace Database\Factories;

use App\Enums\EmergencyDeliveryStatus;
use App\Models\Emergency;
use App\Models\EmergencyDelivery;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyDelivery>
 */
class EmergencyDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'emergency_id' => Emergency::factory(),
            'screen_id' => Screen::factory(),
            'status' => EmergencyDeliveryStatus::Pending,
        ];
    }
}
