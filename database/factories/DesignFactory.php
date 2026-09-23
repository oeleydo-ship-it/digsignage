<?php

namespace Database\Factories;

use App\Enums\DesignStatus;
use App\Models\Design;
use App\Models\Team;
use App\Support\DesignDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Design>
 */
class DesignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->bothify('Layout-###'),
            'description' => null,
            'status' => DesignStatus::Draft,
            'width' => 1920,
            'height' => 1080,
            'version' => 1,
            'document' => DesignDocument::blank(),
        ];
    }
}
