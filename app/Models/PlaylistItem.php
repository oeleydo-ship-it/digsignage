<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistTransition;
use Database\Factories\PlaylistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $playlist_id
 * @property PlaylistItemType $type
 * @property string $title
 * @property int $duration_seconds
 * @property PlaylistTransition $transition
 * @property int $transition_ms
 * @property bool $enabled
 * @property Carbon|null $available_from
 * @property Carbon|null $available_until
 * @property int $position
 * @property int|null $media_id
 * @property int|null $design_id
 * @property int|null $template_id
 * @property string|null $url
 * @property string|null $widget_key
 * @property array<string, mixed>|null $widget_settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Playlist $playlist
 * @property-read Media|null $media
 * @property-read Design|null $design
 * @property-read Template|null $template
 */
#[Fillable([
    'team_id',
    'playlist_id',
    'type',
    'title',
    'duration_seconds',
    'transition',
    'transition_ms',
    'enabled',
    'available_from',
    'available_until',
    'position',
    'media_id',
    'design_id',
    'template_id',
    'url',
    'widget_key',
    'widget_settings',
])]
class PlaylistItem extends Model
{
    /** @use HasFactory<PlaylistItemFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsTo<Design, $this>
     */
    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorArray(?string $teamSlug = null): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'duration_seconds' => $this->duration_seconds,
            'transition' => $this->transition->value,
            'transition_ms' => $this->transition_ms,
            'enabled' => $this->enabled,
            'available_from' => $this->available_from?->toIso8601String(),
            'available_until' => $this->available_until?->toIso8601String(),
            'position' => $this->position,
            'media_id' => $this->media_id,
            'design_id' => $this->design_id,
            'template_id' => $this->template_id,
            'url' => $this->url,
            'widget_key' => $this->widget_key,
            'widget_settings' => $this->widget_settings ?? [],
            'preview_url' => $teamSlug ? $this->previewUrl($teamSlug) : null,
            'media_type' => $this->media?->type?->value,
        ];
    }

    /**
     * Thumbnail used in the playlist editor storyboard.
     */
    public function previewUrl(string $teamSlug): ?string
    {
        return match ($this->type) {
            PlaylistItemType::Media => $this->media?->previewUrl($teamSlug),
            PlaylistItemType::Template => $this->template?->previewUrl($teamSlug),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlaylistItemType::class,
            'transition' => PlaylistTransition::class,
            'duration_seconds' => 'integer',
            'transition_ms' => 'integer',
            'enabled' => 'boolean',
            'position' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'widget_settings' => 'array',
        ];
    }
}
