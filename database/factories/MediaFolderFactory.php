<?php

namespace Database\Factories;

use App\Models\MediaFolder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->company().' Folder',
            'path' => '/',
            'depth' => 0,
        ];
    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (MediaFolder $folder) {
            $folder->rebuildPath();
        });
    }
}
