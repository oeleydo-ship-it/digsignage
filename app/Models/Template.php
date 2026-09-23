<?php

namespace App\Models;

use App\Concerns\HasContentApproval;
use App\Enums\TemplateCategory;
use App\Enums\TemplateStatus;
use App\Support\DesignDocument;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $team_id
 * @property int|null $source_design_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property TemplateCategory $category
 * @property TemplateStatus $status
 * @property int $width
 * @property int $height
 * @property array<string, mixed> $document
 * @property string|null $thumbnail_path
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $team
 * @property-read Design|null $sourceDesign
 *
 * @method static Builder<static> visibleTo(User $user, Team $team)
 */
#[Fillable([
    'team_id',
    'source_design_id',
    'created_by',
    'updated_by',
    'name',
    'slug',
    'description',
    'category',
    'status',
    'width',
    'height',
    'document',
    'thumbnail_path',
    'published_at',
    'archived_at',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasContentApproval, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Design, $this>
     */
    public function sourceDesign(): BelongsTo
    {
        return $this->belongsTo(Design::class, 'source_design_id');
    }

    /**
     * Determine if this is a platform-wide template.
     */
    public function isPlatform(): bool
    {
        return $this->team_id === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedDocument(): array
    {
        return DesignDocument::normalize($this->document ?? [], $this->width, $this->height);
    }

    /**
     * Public preview image when a thumbnail has been generated.
     */
    public function previewUrl(string $teamSlug): ?string
    {
        if (! filled($this->thumbnail_path)) {
            return null;
        }

        return route('templates.thumbnail', [
            'current_team' => $teamSlug,
            'template' => $this,
        ]);
    }

    /**
     * Storage disk used for generated template thumbnails.
     */
    public function disk(): string
    {
        return (string) config('media.disk');
    }

    /**
     * Delete the generated thumbnail file when present.
     */
    public function deleteStoredFiles(): void
    {
        if (filled($this->thumbnail_path)) {
            Storage::disk($this->disk())->delete($this->thumbnail_path);
        }
    }

    /**
     * Templates a team member may list: own team rows, plus published platform rows.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user, Team $team): Builder
    {
        return $query->where(function (Builder $inner) use ($user, $team) {
            $inner->where('team_id', $team->id);

            if ($user->is_platform_admin) {
                $inner->orWhereNull('team_id');
            } else {
                $inner->orWhere(function (Builder $platform) {
                    $platform->whereNull('team_id')
                        ->where('status', TemplateStatus::Published->value);
                });
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TemplateCategory::class,
            'status' => TemplateStatus::class,
            'width' => 'integer',
            'height' => 'integer',
            'document' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
