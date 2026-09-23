<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $parent_id
 * @property LocationType $type
 * @property string $name
 * @property string|null $description
 * @property string|null $address
 * @property string|null $timezone
 * @property string $path
 * @property int $depth
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $parent
 * @property-read Collection<int, Location> $children
 * @property-read Collection<int, Screen> $screens
 */
#[Fillable([
    'team_id',
    'parent_id',
    'type',
    'name',
    'description',
    'address',
    'timezone',
    'path',
    'depth',
    'tags',
    'metadata',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the parent location.
     *
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child locations.
     *
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get screens at this location.
     *
     * @return HasMany<Screen, $this>
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    /**
     * Rebuild the materialized path and depth from the parent.
     */
    public function rebuildPath(): void
    {
        if ($this->parent_id === null) {
            $this->path = '/';
            $this->depth = 0;

            return;
        }

        $parent = $this->parent()->firstOrFail();

        $this->path = $parent->path.$parent->id.'/';
        $this->depth = $parent->depth + 1;
    }

    /**
     * Get the path prefix that identifies this node and its descendants.
     */
    public function descendantPathPrefix(): string
    {
        return $this->path.$this->id.'/';
    }

    /**
     * Determine if the given location is this node or a descendant.
     */
    public function isAncestorOf(self $location): bool
    {
        return str_starts_with($location->path, $this->descendantPathPrefix());
    }

    /**
     * Resolved timezone, walking up the tree when unset.
     */
    public function resolvedTimezone(): ?string
    {
        if (filled($this->timezone)) {
            return $this->timezone;
        }

        return $this->parent?->resolvedTimezone();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'tags' => 'array',
            'metadata' => 'array',
            'depth' => 'integer',
        ];
    }
}
