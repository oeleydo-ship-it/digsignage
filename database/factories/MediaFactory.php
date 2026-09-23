<?php

namespace Database\Factories;

use App\Enums\MediaProcessingStatus;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('Asset-###');

        return [
            'team_id' => Team::factory(),
            'folder_id' => null,
            'type' => MediaType::Image,
            'source' => MediaSource::File,
            'name' => $name,
            'filename' => $name.'.png',
            'original_filename' => $name.'.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'processing_status' => MediaProcessingStatus::Ready,
            'usage_count' => 0,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the media is an external URL.
     */
    public function url(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::Url,
            'source' => MediaSource::External,
            'filename' => null,
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'external_url' => 'https://example.com/promo',
            'processing_status' => MediaProcessingStatus::Ready,
        ]);
    }

    /**
     * Indicate that the media is an uploaded video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::Video,
            'filename' => ($attributes['name'] ?? 'clip').'.mp4',
            'original_filename' => ($attributes['name'] ?? 'clip').'.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => 2048,
            'duration' => 12,
        ]);
    }

    /**
     * Indicate that the media is an external video URL.
     */
    public function videoUrl(string $url = 'https://example.com/promo.mp4'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::Video,
            'source' => MediaSource::External,
            'filename' => null,
            'original_filename' => null,
            'mime_type' => 'video/mp4',
            'file_size' => null,
            'storage_path' => null,
            'external_url' => $url,
            'processing_status' => MediaProcessingStatus::Ready,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
