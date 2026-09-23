<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ScheduleTargetType;
use Database\Factories\ScheduleTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $schedule_id
 * @property ScheduleTargetType $target_type
 * @property int|null $screen_id
 * @property int|null $screen_group_id
 * @property int|null $location_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Schedule $schedule
 * @property-read Screen|null $screen
 * @property-read ScreenGroup|null $screenGroup
 * @property-read Location|null $location
 */
#[Fillable([
    'team_id',
    'schedule_id',
    'target_type',
    'screen_id',
    'screen_group_id',
    'location_id',
])]
class ScheduleTarget extends Model
{
    /** @use HasFactory<ScheduleTargetFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<ScreenGroup, $this>
     */
    public function screenGroup(): BelongsTo
    {
        return $this->belongsTo(ScreenGroup::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type->value,
            'target_type_label' => $this->target_type->label(),
            'screen_id' => $this->screen_id,
            'screen_group_id' => $this->screen_group_id,
            'location_id' => $this->location_id,
            'label' => match ($this->target_type) {
                ScheduleTargetType::Screen => $this->screen !== null ? $this->screen->name : 'Screen',
                ScheduleTargetType::ScreenGroup => $this->screenGroup !== null ? $this->screenGroup->name : 'Group',
                ScheduleTargetType::Location => $this->location !== null ? $this->location->name : 'Location',
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => ScheduleTargetType::class,
        ];
    }
}
