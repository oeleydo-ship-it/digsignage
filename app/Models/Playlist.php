<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasContentApproval;
use App\Enums\PlaylistStatus;
use App\Support\PlaylistItems;
use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $name
 * @property string|null $description
 * @property PlaylistStatus $status
 * @property bool $loop
 * @property int $version
 * @property int $duration_seconds
 * @property int|null $items_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, PlaylistItem> $items
 * @property-read Collection<int, PlaylistRevision> $revisions
 */
#[Fillable([
    'team_id',
    'created_by',
    'updated_by',
    'name',
    'description',
    'status',
    'loop',
    'version',
    'duration_seconds',
    'published_at',
])]
class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use BelongsToTeam, HasContentApproval, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<PlaylistRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PlaylistRevision::class)->orderByDesc('version');
    }

    /**
     * Snapshot of current items for revisions and comparison.
     *
     * @return list<array<string, mixed>>
     */
    public function itemsSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->items as $item) {
            $snapshot[] = [
                'type' => $item->type->value,
                'title' => $item->title,
                'duration_seconds' => $item->duration_seconds,
                'transition' => $item->transition->value,
                'transition_ms' => $item->transition_ms,
                'enabled' => $item->enabled,
                'available_from' => $item->available_from?->toIso8601String(),
                'available_until' => $item->available_until?->toIso8601String(),
                'position' => $item->position,
                'media_id' => $item->media_id,
                'design_id' => $item->design_id,
                'template_id' => $item->template_id,
                'url' => $item->url,
                'widget_key' => $item->widget_key,
                'widget_settings' => $item->widget_settings ?? [],
            ];
        }

        return $snapshot;
    }

    /**
     * Recalculate stored duration from enabled items.
     */
    public function recalculateDuration(): int
    {
        return PlaylistItems::totalDuration($this->itemsSnapshot());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlaylistStatus::class,
            'loop' => 'boolean',
            'version' => 'integer',
            'duration_seconds' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
