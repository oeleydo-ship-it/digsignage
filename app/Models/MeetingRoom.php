<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\MeetingRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bookable room shown on booking forms and room-status screens.
 *
 * When `external_calendar_id` is set the room mirrors a Microsoft 365 room
 * mailbox: bookings made here are written to that calendar, and meetings
 * booked in Outlook are pulled back in by the scheduled sync.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $location_id
 * @property string $name
 * @property string|null $description
 * @property int|null $capacity
 * @property list<string>|null $amenities
 * @property string $color
 * @property bool $is_active
 * @property bool $public_booking_enabled
 * @property bool $requires_approval
 * @property string $booking_token
 * @property int $min_duration_minutes
 * @property int $max_duration_minutes
 * @property string $opens_at
 * @property string $closes_at
 * @property int|null $calendar_connection_id
 * @property string|null $external_calendar_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $location
 * @property-read CalendarConnection|null $calendarConnection
 * @property-read Collection<int, RoomBooking> $bookings
 *
 * @method static Builder<static> active()
 */
#[Fillable([
    'team_id',
    'location_id',
    'name',
    'description',
    'capacity',
    'amenities',
    'color',
    'is_active',
    'public_booking_enabled',
    'requires_approval',
    'booking_token',
    'min_duration_minutes',
    'max_duration_minutes',
    'opens_at',
    'closes_at',
    'calendar_connection_id',
    'external_calendar_id',
])]
class MeetingRoom extends Model
{
    /** @use HasFactory<MeetingRoomFactory> */
    use BelongsToTeam, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MeetingRoom $room): void {
            if (blank($room->booking_token)) {
                $room->booking_token = self::newBookingToken();
            }
        });
    }

    /**
     * Unguessable token for the room's public booking link.
     */
    public static function newBookingToken(): string
    {
        return Str::random(40);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<CalendarConnection, $this>
     */
    public function calendarConnection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class);
    }

    /**
     * @return HasMany<RoomBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether bookings are mirrored to a Microsoft 365 room calendar.
     */
    public function isLinkedToMicrosoft(): bool
    {
        return filled($this->external_calendar_id)
            && $this->calendarConnection !== null
            && $this->calendarConnection->is_active;
    }

    /**
     * Public booking form URL, or null when public booking is switched off.
     */
    public function publicBookingUrl(): ?string
    {
        if (! $this->is_active || ! $this->public_booking_enabled) {
            return null;
        }

        return route('rooms.public.show', $this->booking_token);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'public_booking_enabled' => 'boolean',
            'requires_approval' => 'boolean',
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
        ];
    }
}
