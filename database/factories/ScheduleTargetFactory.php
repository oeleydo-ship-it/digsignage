<?php

namespace Database\Factories;

use App\Enums\ScheduleTargetType;
use App\Models\Schedule;
use App\Models\ScheduleTarget;
use App\Models\Screen;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleTarget>
 */
class ScheduleTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'schedule_id' => Schedule::factory(),
            'target_type' => ScheduleTargetType::Screen,
            'screen_id' => Screen::factory(),
        ];
    }
}
