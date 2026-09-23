<?php

namespace Database\Factories;

use App\Models\QueuePriority;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueuePriority>
 */
class QueuePriorityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        $name = is_array($words) ? implode(' ', $words) : (string) $words;

        return [
            'team_id' => Team::factory(),
            'name' => Str::title($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'weight' => fake()->numberBetween(0, 50),
            'color' => '#64748b',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
