<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use Database\Factories\RoomBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $meeting_room_id
 * @property string $title
 * @property string|null $organizer_name
 * @property string|null $organizer_email
 * @property int|null $attendees
 * @property string|null $notes
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property RoomBookingStatus $status
 * @property RoomBookingSource $source
 * @property string|null $external_id
 * @property string|null $external_change_key
 * @property int|null $created_by
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $cancelled_at
 * @property string|null $sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MeetingRoom $room
 * @property-read User|null $creator
 *
 * @method static Builder<static> blocking()
 * @method static Builder<static> overlapping(\DateTimeInterface $start, \DateTimeInterface $end)
 */
#[Fillable([
    'team_id',
    'meeting_room_id',
    'title',
    'organizer_name',
    'organizer_email',
    'attendees',
    'notes',
    'starts_at',
    'ends_at',
    'status',
    'source',
    'external_id',
    'external_change_key',
    'created_by',
    'checked_in_at',
    'cancelled_at',
    'sync_error',
])]
class RoomBooking extends Model
{
    /** @use HasFactory<RoomBookingFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<MeetingRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Bookings that currently hold the room.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', RoomBookingStatus::blocking());
    }

    /**
     * Bookings sharing any time with [start, end). Back-to-back meetings,
     * where one ends exactly as the next starts, do not overlap.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function isActive(): bool
    {
        return in_array($this->status->value, RoomBookingStatus::blocking(), true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => RoomBookingStatus::class,
            'source' => RoomBookingSource::class,
            'attendees' => 'integer',
        ];
    }
}
