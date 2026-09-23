<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\EmergencySeverity;
use App\Enums\EmergencyStatus;
use Database\Factories\EmergencyFactory;
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
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $started_by
 * @property int|null $stopped_by
 * @property int|null $image_id
 * @property int|null $video_id
 * @property string $title
 * @property string|null $message
 * @property string|null $instructions
 * @property string|null $background
 * @property EmergencySeverity $severity
 * @property EmergencyStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $stopped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Media|null $image
 * @property-read Media|null $video
 * @property-read User|null $creator
 * @property-read Collection<int, EmergencyTarget> $targets
 * @property-read Collection<int, EmergencyDelivery> $deliveries
 * @property-read Collection<int, EmergencyAudit> $audits
 */
#[Fillable([
    'team_id',
    'created_by',
    'updated_by',
    'started_by',
    'stopped_by',
    'image_id',
    'video_id',
    'title',
    'message',
    'instructions',
    'background',
    'severity',
    'status',
    'starts_at',
    'expires_at',
    'started_at',
    'stopped_at',
])]
class Emergency extends Model
{
    /** @use HasFactory<EmergencyFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function stopper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_id');
    }

    /**
     * @return HasMany<EmergencyTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(EmergencyTarget::class);
    }

    /**
     * @return HasMany<EmergencyDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EmergencyDelivery::class);
    }

    /**
     * @return HasMany<EmergencyAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(EmergencyAudit::class);
    }

    /**
     * Overlay background color for players.
     */
    public function overlayBackground(): string
    {
        return filled($this->background) ? $this->background : $this->severity->defaultBackground();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'severity' => EmergencySeverity::class,
            'status' => EmergencyStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }
}
