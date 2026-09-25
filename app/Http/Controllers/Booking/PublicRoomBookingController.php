<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\CreateRoomBooking;
use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingDetails;
use App\Http\Requests\Booking\PublicRoomBookingRequest;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Support\RoomAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest-facing booking form reached from a room's link or its screen QR code.
 *
 * The URL carries an unguessable per-room token, so only people given the
 * link can book, and rotating the token revokes every old link at once.
 */
class PublicRoomBookingController extends Controller
{
    public function show(Request $request, MeetingRoom $meetingRoom): Response
    {
        $this->assertOpen($meetingRoom);

        $meetingRoom->loadMissing(['location.parent', 'team:id,name']);
        $timezone = RoomAvailability::timezone($meetingRoom);
        $today = CarbonImmutable::now($timezone);
        $date = $this->requestedDate($request, $today);

        return Inertia::render('bookings/public-book', [
            'room' => [
                'name' => $meetingRoom->name,
                'description' => $meetingRoom->description,
                'capacity' => $meetingRoom->capacity,
                'amenities' => $meetingRoom->amenities ?? [],
                'color' => $meetingRoom->color,
                'location' => $meetingRoom->location?->name,
                'organization' => $meetingRoom->team->name,
                'timezone' => $timezone,
                'opens_at' => $meetingRoom->opens_at,
                'closes_at' => $meetingRoom->closes_at,
                'min_duration' => $meetingRoom->min_duration_minutes,
                'max_duration' => $meetingRoom->max_duration_minutes,
                'requires_approval' => $meetingRoom->requires_approval,
                'token' => $meetingRoom->booking_token,
            ],
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'lastDate' => $today->addDays(60)->toDateString(),
            // Busy slots only: guests see when the room is taken, not by whom.
            // Minutes past local midnight, clipped to this day, so meetings
            // running in from yesterday or on into tomorrow still block
            // exactly the part of the day they cover.
            'busy' => RoomAvailability::day($meetingRoom, $date)
                ->map(fn (RoomBooking $booking) => [
                    'start' => (int) max(0, $date->diffInMinutes($booking->starts_at, false)),
                    'end' => (int) min(24 * 60, $date->diffInMinutes($booking->ends_at, false)),
                    'pending' => $booking->status === RoomBookingStatus::Pending,
                ])
                ->values(),
            'confirmation' => $request->session()->get('room_booking_confirmation'),
        ]);
    }

    public function store(PublicRoomBookingRequest $request, MeetingRoom $meetingRoom, CreateRoomBooking $createBooking): RedirectResponse
    {
        $this->assertOpen($meetingRoom);

        $meetingRoom->loadMissing(['location.parent', 'calendarConnection']);
        $timezone = RoomAvailability::timezone($meetingRoom);
        $latest = CarbonImmutable::now($timezone)->addDays(60)->endOfDay();
        $start = RoomAvailability::localToUtc($meetingRoom, (string) $request->validated('date'), (string) $request->validated('start_time'));
        $end = $start->addMinutes((int) $request->validated('duration'));

        if ($start->greaterThan($latest)) {
            throw ValidationException::withMessages(['date' => __('Bookings can be made up to 60 days ahead.')]);
        }

        $booking = $createBooking->handle($meetingRoom, [
            ...BookingDetails::from($request),
            'starts_at' => $start,
            'ends_at' => $end,
        ], RoomBookingSource::PublicForm);

        return redirect()
            ->route('rooms.public.show', ['meetingRoom' => $meetingRoom->booking_token, 'date' => $request->validated('date')])
            ->with('room_booking_confirmation', [
                'title' => $booking->title,
                'date' => $booking->starts_at->setTimezone($timezone)->isoFormat('dddd D MMMM'),
                'start' => $booking->starts_at->setTimezone($timezone)->format('H:i'),
                'end' => $booking->ends_at->setTimezone($timezone)->format('H:i'),
                'pending' => $booking->status === RoomBookingStatus::Pending,
            ]);
    }

    protected function assertOpen(MeetingRoom $room): void
    {
        abort_unless($room->is_active && $room->public_booking_enabled, 404);
    }

    protected function requestedDate(Request $request, CarbonImmutable $today): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $request->string('date')->toString(), $today->getTimezone());
        } catch (\Throwable) {
            return $today->startOfDay();
        }

        if ($date === null || $date->lessThan($today->startOfDay()) || $date->greaterThan($today->addDays(60))) {
            return $today->startOfDay();
        }

        return $date->startOfDay();
    }
}
