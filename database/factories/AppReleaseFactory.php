<?php

namespace Database\Factories;

use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use App\Models\AppRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppRelease>
 */
class AppReleaseFactory extends Factory
{
    protected $model = AppRelease::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('1.#.#'),
            'title' => null,
            'notes' => null,
            'features' => ['New feature'],
            'source' => AppReleaseSource::Upload,
            'source_ref' => null,
            'package_path' => null,
            'checksum' => null,
            'package_size' => null,
            'release_path' => null,
            'status' => AppReleaseStatus::Ready,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => AppReleaseStatus::Active,
            'activated_at' => now(),
        ]);
    }
}
