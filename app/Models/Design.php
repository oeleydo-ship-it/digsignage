<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasContentApproval;
use App\Enums\DesignStatus;
use App\Support\DesignDocument;
use Database\Factories\DesignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property DesignStatus $status
 * @property int $width
 * @property int $height
 * @property int $version
 * @property array<string, mixed> $document
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, DesignRevision> $revisions
 */
#[Fillable([
    'team_id',
    'created_by',
    'updated_by',
    'name',
    'description',
    'status',
    'width',
    'height',
    'version',
    'document',
    'published_at',
])]
class Design extends Model
{
    /** @use HasFactory<DesignFactory> */
    use BelongsToTeam, HasContentApproval, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DesignRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DesignRevision::class)->orderByDesc('version');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', DesignStatus::Published);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedDocument(): array
    {
        return DesignDocument::normalize($this->document ?? [], $this->width, $this->height);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DesignStatus::class,
            'width' => 'integer',
            'height' => 'integer',
            'version' => 'integer',
            'document' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
