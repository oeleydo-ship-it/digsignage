<?php

namespace App\Support;

use App\Enums\RoomBookingStatus;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Time maths for rooms: local timezone, opening hours and conflicts.
 *
 * Bookings are stored in UTC. Forms and screens work in the room's local
 * time, taken from its location (walking up the location tree) and falling
 * back to the application timezone.
 */
final class RoomAvailability
{
    public static function timezone(MeetingRoom $room): string
    {
        $timezone = $room->location?->resolvedTimezone();

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }

    /**
     * Parse a local date and HH:MM time in the room's timezone into UTC.
     */
    public static function localToUtc(MeetingRoom $room, string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, self::timezone($room))->utc();
    }

    /**
     * Blocking bookings that overlap [start, end), optionally ignoring one.
     *
     * @return Collection<int, RoomBooking>
     */
    public static function conflicts(MeetingRoom $room, DateTimeInterface $start, DateTimeInterface $end, ?int $ignoreId = null): Collection
    {
        return RoomBooking::query()
            ->where('meeting_room_id', $room->id)
            ->blocking()
            ->overlapping($start, $end)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Whether [start, end) sits inside the room's opening hours on one local day.
     */
    public static function withinOpeningHours(MeetingRoom $room, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        $timezone = self::timezone($room);
        $localStart = CarbonImmutable::instance($start)->setTimezone($timezone);
        $localEnd = CarbonImmutable::instance($end)->setTimezone($timezone);

        if ($localStart->toDateString() !== $localEnd->copy()->subSecond()->toDateString()) {
            return false;
        }

        return $localStart->format('H:i') >= $room->opens_at
            && $localEnd->format('H:i') <= ($room->closes_at === '00:00' ? '24:00' : $room->closes_at);
    }

    /**
     * Blocking bookings for one local calendar day.
     *
     * @return Collection<int, RoomBooking>
     */
    public static function day(MeetingRoom $room, CarbonImmutable $localDay): Collection
    {
        return self::days($room, $localDay, 1);
    }

    /**
     * Blocking bookings overlapping a run of local days, starting at the
     * given day, in the room's timezone.
     *
     * @return Collection<int, RoomBooking>
     */
    public static function days(MeetingRoom $room, CarbonImmutable $firstLocalDay, int $days): Collection
    {
        $timezone = self::timezone($room);
        $start = CarbonImmutable::parse($firstLocalDay->toDateString(), $timezone)->startOfDay();

        return RoomBooking::query()
            ->where('meeting_room_id', $room->id)
            ->blocking()
            ->overlapping($start->utc(), $start->addDays(max(1, $days))->utc())
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Data a room-status screen needs: today's schedule in the room's timezone.
     * The player works out "in use / next" itself every second from these.
     *
     * @return array<string, mixed>
     */
    public static function screenPayload(MeetingRoom $room, ?CarbonImmutable $now = null): array
    {
        $timezone = self::timezone($room);
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);

        return [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'color' => $room->color,
                'amenities' => $room->amenities ?? [],
                'location' => $room->location?->name,
                'is_active' => $room->is_active,
            ],
            'room_timezone' => $timezone,
            'booking_url' => $room->publicBookingUrl(),
            'microsoft' => $room->isLinkedToMicrosoft(),
            'bookings' => self::day($room, $now)
                ->filter(fn (RoomBooking $booking) => $booking->status === RoomBookingStatus::Confirmed)
                ->map(fn (RoomBooking $booking) => [
                    'id' => $booking->id,
                    'title' => $booking->title,
                    'organizer' => $booking->organizer_name,
                    'starts_at' => $booking->starts_at->toIso8601String(),
                    'ends_at' => $booking->ends_at->toIso8601String(),
                    'start_label' => $booking->starts_at->setTimezone($timezone)->format('H:i'),
                    'end_label' => $booking->ends_at->setTimezone($timezone)->format('H:i'),
                ])
                ->values()
                ->all(),
        ];
    }
}
