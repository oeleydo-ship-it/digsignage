<?php

namespace Database\Factories;

use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Models\Team;
use App\Models\Template;
use App\Support\DesignDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->bothify('Template-###'),
            'slug' => null,
            'description' => null,
            'category' => TemplateCategory::Corporate,
            'status' => TemplateStatus::Draft,
            'width' => 1920,
            'height' => 1080,
            'document' => DesignDocument::blank(),
        ];
    }

    /**
     * Indicate that the template is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TemplateStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the template is a platform catalog item.
     */
    public function platform(): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => null,
        ]);
    }
}
