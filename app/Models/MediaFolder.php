<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\MediaFolderFactory;
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
 * @property string $name
 * @property string $path
 * @property int $depth
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaFolder|null $parent
 * @property-read Collection<int, MediaFolder> $children
 * @property-read Collection<int, Media> $media
 */
#[Fillable(['team_id', 'parent_id', 'name', 'path', 'depth'])]
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
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
     * Determine if this folder is an ancestor of another folder.
     */
    public function isAncestorOf(self $folder): bool
    {
        return str_starts_with($folder->path, $this->descendantPathPrefix());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
        ];
    }
}
