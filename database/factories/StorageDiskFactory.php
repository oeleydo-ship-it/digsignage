<?php

namespace Database\Factories;

use App\Enums\StorageProvider;
use App\Models\StorageDisk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageDisk>
 */
class StorageDiskFactory extends Factory
{
    protected $model = StorageDisk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'created_by' => null,
            'name' => fake()->unique()->domainWord().' storage',
            'provider' => StorageProvider::Local,
            'bucket' => null,
            'region' => null,
            'root' => null,
            'endpoint' => null,
            'url' => null,
            'access_key' => null,
            'secret_key' => null,
            'path_style_endpoint' => false,
            'visibility' => 'private',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function local(?string $root = null): static
    {
        return $this->state(fn () => [
            'provider' => StorageProvider::Local,
            'root' => $root ?? storage_path('app/private/factory-storage'),
        ]);
    }

    public function wasabi(): static
    {
        return $this->state(fn () => [
            'provider' => StorageProvider::Wasabi,
            'bucket' => 'digsignage-media',
            'region' => 'us-east-1',
            'access_key' => 'AKIAEXAMPLE',
            'secret_key' => 'secret-example',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true, 'team_id' => null]);
    }
}
