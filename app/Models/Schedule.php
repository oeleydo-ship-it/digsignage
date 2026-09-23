<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use Database\Factories\ScheduleFactory;
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
 * @property int|null $channel_id
 * @property int|null $playlist_id
 * @property string $name
 * @property string|null $description
 * @property ScheduleContentType $content_type
 * @property string $timezone
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $start_time
 * @property string $end_time
 * @property ScheduleRecurrence $recurrence
 * @property list<int>|null $weekdays
 * @property int $priority
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Channel|null $channel
 * @property-read Playlist|null $playlist
 * @property-read Collection<int, ScheduleTarget> $targets
 */
#[Fillable([
    'team_id',
    'created_by',
    'updated_by',
    'channel_id',
    'playlist_id',
    'name',
    'description',
    'content_type',
    'timezone',
    'starts_on',
    'ends_on',
    'start_time',
    'end_time',
    'recurrence',
    'weekdays',
    'priority',
    'is_enabled',
])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Playlist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * @return HasMany<ScheduleTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ScheduleTarget::class);
    }

    /**
     * Highest target specificity for this schedule.
     */
    public function specificity(): int
    {
        $max = 0;

        foreach ($this->targets as $target) {
            $max = max($max, $target->target_type->specificity());
        }

        return $max;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'content_type' => ScheduleContentType::class,
            'recurrence' => ScheduleRecurrence::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'weekdays' => 'array',
            'priority' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
