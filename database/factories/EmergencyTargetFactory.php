<?php

namespace Database\Factories;

use App\Enums\EmergencyTargetType;
use App\Models\Emergency;
use App\Models\EmergencyTarget;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyTarget>
 */
class EmergencyTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'emergency_id' => Emergency::factory(),
            'target_type' => EmergencyTargetType::Screen,
            'screen_id' => Screen::factory(),
            'location_id' => null,
        ];
    }
}
