<?php

namespace App\Models;

use App\Enums\StorageProvider;
use Database\Factories\StorageDiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A storage backend configured by a platform administrator.
 *
 * A disk with a null `team_id` is available to every organization; a disk
 * scoped to a team is only selectable for that organization.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $created_by
 * @property string $name
 * @property StorageProvider $provider
 * @property string|null $bucket
 * @property string|null $region
 * @property string|null $root
 * @property string|null $endpoint
 * @property string|null $url
 * @property string|null $access_key
 * @property string|null $secret_key
 * @property bool $path_style_endpoint
 * @property string $visibility
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $last_tested_at
 * @property string|null $last_test_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read Collection<int, Team> $assignedTeams
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'team_id',
    'created_by',
    'name',
    'provider',
    'bucket',
    'region',
    'root',
    'endpoint',
    'url',
    'access_key',
    'secret_key',
    'path_style_endpoint',
    'visibility',
    'is_default',
    'is_active',
])]
class StorageDisk extends Model
{
    /** @use HasFactory<StorageDiskFactory> */
    use HasFactory;

    /**
     * Prefix for the generated `filesystems.disks` key.
     */
    public const NAME_PREFIX = 'storage-';

    /**
     * @var list<string>
     */
    protected $hidden = ['access_key', 'secret_key'];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Organizations whose uploads are routed to this backend.
     *
     * @return HasMany<Team, $this>
     */
    public function assignedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'storage_disk_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Key this disk is registered under in `filesystems.disks`.
     */
    public function diskName(): string
    {
        return self::NAME_PREFIX.$this->id;
    }

    /**
     * Build the Laravel filesystem configuration for this backend.
     *
     * @return array<string, mixed>
     */
    public function toDiskConfig(): array
    {
        $root = trim((string) $this->root, '/');

        if ($this->provider === StorageProvider::Local) {
            return [
                'driver' => 'local',
                'root' => $root === '' ? storage_path('app/private/media') : $root,
                'visibility' => $this->visibility,
                'throw' => false,
                'report' => false,
            ];
        }

        return array_filter([
            'driver' => 's3',
            'key' => $this->access_key,
            'secret' => $this->secret_key,
            'region' => $this->region ?: 'us-east-1',
            'bucket' => $this->bucket,
            'root' => $root === '' ? null : $root,
            'url' => $this->url ?: null,
            'endpoint' => $this->provider->endpointFor($this->endpoint, $this->region),
            'use_path_style_endpoint' => $this->path_style_endpoint,
            'visibility' => $this->visibility,
            'throw' => false,
            'report' => false,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, string|bool|null>
     */
    protected function casts(): array
    {
        return [
            'provider' => StorageProvider::class,
            'access_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'path_style_endpoint' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
