<?php

namespace Database\Factories;

use App\Models\QueueSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueSetting>
 */
class QueueSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'settings' => [],
        ];
    }
}
