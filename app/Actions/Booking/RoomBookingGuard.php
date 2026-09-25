<?php

namespace App\Actions\Booking;

use App\Models\MeetingRoom;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use App\Support\RoomAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Rules every booking must pass before it holds a room.
 */
class RoomBookingGuard
{
    /**
     * @param  bool  $strict  Apply the self-service rules (opening hours,
     *                        duration limits, capacity, no past starts) that
     *                        staff are allowed to override.
     */
    public function assertBookable(
        MeetingRoom $room,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $attendees = null,
        bool $strict = true,
        ?int $ignoreBookingId = null,
        ?string $ignoreExternalId = null,
    ): void {
        if (! $room->is_active) {
            throw ValidationException::withMessages(['meeting_room_id' => __('This room is not available for booking.')]);
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => __('The meeting must end after it starts.')]);
        }

        if ($end->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['starts_at' => __('That time has already passed.')]);
        }

        if ($strict) {
            $this->assertSelfServiceRules($room, $start, $end, $attendees);
        }

        $clash = RoomAvailability::conflicts($room, $start, $end, $ignoreBookingId)->first();

        if ($clash !== null) {
            $timezone = RoomAvailability::timezone($room);

            throw ValidationException::withMessages([
                'starts_at' => __(':room is already booked :from–:to (:title).', [
                    'room' => $room->name,
                    'from' => $clash->starts_at->setTimezone($timezone)->format('H:i'),
                    'to' => $clash->ends_at->setTimezone($timezone)->format('H:i'),
                    'title' => $clash->title,
                ]),
            ]);
        }

        $this->assertFreeInMicrosoft365($room, $start, $end, $ignoreExternalId);
    }

    protected function assertSelfServiceRules(MeetingRoom $room, CarbonImmutable $start, CarbonImmutable $end, ?int $attendees): void
    {
        if ($start->lessThan(CarbonImmutable::now()->subMinutes(5))) {
            throw ValidationException::withMessages(['starts_at' => __('Choose a start time in the future.')]);
        }

        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes < $room->min_duration_minutes || $minutes > $room->max_duration_minutes) {
            throw ValidationException::withMessages([
                'ends_at' => __('Bookings for this room must last between :min and :max minutes.', [
                    'min' => $room->min_duration_minutes,
                    'max' => $room->max_duration_minutes,
                ]),
            ]);
        }

        if (! RoomAvailability::withinOpeningHours($room, $start, $end)) {
            throw ValidationException::withMessages([
                'starts_at' => __('This room can be booked between :open and :close.', [
                    'open' => $room->opens_at,
                    'close' => $room->closes_at,
                ]),
            ]);
        }

        if ($attendees !== null && $room->capacity !== null && $attendees > $room->capacity) {
            throw ValidationException::withMessages([
                'attendees' => __(':room seats up to :capacity people.', [
                    'room' => $room->name,
                    'capacity' => $room->capacity,
                ]),
            ]);
        }
    }

    /**
     * The local copy is refreshed every few minutes; ask Microsoft 365 about
     * this exact slot so an Outlook booking made seconds ago still wins.
     */
    protected function assertFreeInMicrosoft365(MeetingRoom $room, CarbonImmutable $start, CarbonImmutable $end, ?string $ignoreExternalId): void
    {
        $room->loadMissing('calendarConnection');

        if (! $room->isLinkedToMicrosoft()) {
            return;
        }

        try {
            $events = MicrosoftGraphClient::for($room->calendarConnection)
                ->calendarView((string) $room->external_calendar_id, $start, $end);
        } catch (MicrosoftGraphException $exception) {
            throw ValidationException::withMessages([
                'meeting_room_id' => __('Microsoft 365 could not confirm the room is free: :error', ['error' => $exception->getMessage()]),
            ]);
        }

        foreach ($events as $event) {
            if ($event['cancelled'] || $event['free'] || $event['id'] === $ignoreExternalId) {
                continue;
            }

            if ($event['starts_at']->lessThan($end) && $event['ends_at']->greaterThan($start)) {
                throw ValidationException::withMessages([
                    'starts_at' => __(':room is already booked in Microsoft 365 at that time.', ['room' => $room->name]),
                ]);
            }
        }
    }
}
