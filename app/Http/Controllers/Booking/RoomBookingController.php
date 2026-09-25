<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\ApproveRoomBooking;
use App\Actions\Booking\CancelRoomBooking;
use App\Actions\Booking\CreateRoomBooking;
use App\Actions\Booking\UpdateRoomBooking;
use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingDetails;
use App\Http\Requests\Booking\SaveRoomBookingRequest;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use App\Support\RoomAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoomBookingController extends Controller
{
    /**
     * Day view of every room with its bookings, plus requests awaiting approval.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RoomBooking::class);

        $user = $request->user();
        $team = $user->currentTeam;
        $date = $this->requestedDate($request);
        $view = in_array($request->string('view')->toString(), ['week', 'month'], true)
            ? $request->string('view')->toString()
            : 'day';
        [$rangeStart, $rangeEnd] = $this->range($date, $view);
        $roomFilter = $request->integer('room') ?: null;

        $rooms = MeetingRoom::query()
            ->forTeam($team)
            ->with(['location.parent', 'calendarConnection'])
            ->when($roomFilter, fn ($query) => $query->whereKey($roomFilter))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $days = (int) $rangeStart->diffInDays($rangeEnd) + 1;
        $bookings = $rooms->flatMap(
            fn (MeetingRoom $room) => RoomAvailability::days($room, $rangeStart, $days)
                ->each(fn (RoomBooking $booking) => $booking->setRelation('room', $room)),
        );

        $pending = RoomBooking::query()
            ->forTeam($team)
            ->with('room.location.parent')
            ->where('status', RoomBookingStatus::Pending)
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        return Inertia::render('bookings/index', [
            'date' => $date,
            'view' => $view,
            'range' => ['start' => $rangeStart->toDateString(), 'end' => $rangeEnd->toDateString()],
            'today' => CarbonImmutable::now(config('app.timezone'))->toDateString(),
            'filters' => ['room' => $roomFilter],
            'rooms' => $rooms->map(fn (MeetingRoom $room) => $this->roomPayload($room))->values(),
            'allRooms' => MeetingRoom::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (MeetingRoom $room) => ['id' => $room->id, 'name' => $room->name]),
            'bookings' => $bookings->map(fn (RoomBooking $booking) => $this->bookingPayload($booking, $user))->values(),
            'pending' => $pending->map(fn (RoomBooking $booking) => $this->bookingPayload($booking, $user))->values(),
            'permissions' => $user->toBookingPermissions($team),
        ]);
    }

    public function store(SaveRoomBookingRequest $request, CreateRoomBooking $createBooking): RedirectResponse
    {
        Gate::authorize('create', RoomBooking::class);

        $room = $this->roomFor($request, $request->integer('meeting_room_id'));
        [$start, $end] = $this->times($room, $request->validated());

        $createBooking->handle($room, [
            ...BookingDetails::from($request),
            'starts_at' => $start,
            'ends_at' => $end,
        ], RoomBookingSource::Manual, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':room booked.', ['room' => $room->name])]);

        return back();
    }

    public function update(
        SaveRoomBookingRequest $request,
        string $current_team,
        RoomBooking $roomBooking,
        UpdateRoomBooking $updateBooking,
    ): RedirectResponse {
        $this->assertTeam($request, $current_team, $roomBooking);
        Gate::authorize('update', $roomBooking);

        $room = $this->roomFor($request, $request->integer('meeting_room_id'));
        [$start, $end] = $this->times($room, $request->validated());

        $updateBooking->handle($roomBooking, [
            ...BookingDetails::from($request),
            'meeting_room_id' => $room->id,
            'starts_at' => $start,
            'ends_at' => $end,
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }

    public function cancel(Request $request, string $current_team, RoomBooking $roomBooking, CancelRoomBooking $cancelBooking): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $roomBooking);
        Gate::authorize('cancel', $roomBooking);

        $cancelBooking->handle($roomBooking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking cancelled.')]);

        return back();
    }

    public function approve(Request $request, string $current_team, RoomBooking $roomBooking, ApproveRoomBooking $approveBooking): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $roomBooking);
        Gate::authorize('approve', $roomBooking);

        $approveBooking->handle($roomBooking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking approved.')]);

        return back();
    }

    public function decline(Request $request, string $current_team, RoomBooking $roomBooking, CancelRoomBooking $cancelBooking): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $roomBooking);
        Gate::authorize('approve', $roomBooking);

        $cancelBooking->handle($roomBooking, RoomBookingStatus::Declined);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request declined.')]);

        return back();
    }

    /**
     * First and last day shown: the day itself, its Monday–Sunday week, or
     * the whole weeks covering its month (as a calendar grid shows them).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function range(string $date, string $view): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        return match ($view) {
            'week' => [$day->startOfWeek(CarbonImmutable::MONDAY), $day->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()],
            'month' => [
                $day->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
                $day->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
            ],
            default => [$day, $day],
        };
    }

    protected function requestedDate(Request $request): string
    {
        $date = $request->string('date')->toString();

        try {
            return $date !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $date)->toDateString()
                : CarbonImmutable::now(config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return CarbonImmutable::now(config('app.timezone'))->toDateString();
        }
    }

    protected function roomFor(Request $request, int $roomId): MeetingRoom
    {
        return MeetingRoom::query()
            ->forTeam($request->user()->currentTeam)
            ->with(['location.parent', 'calendarConnection'])
            ->findOrFail($roomId);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    protected function times(MeetingRoom $room, array $validated): array
    {
        return [
            RoomAvailability::localToUtc($room, (string) $validated['date'], (string) $validated['start_time']),
            RoomAvailability::localToUtc($room, (string) $validated['date'], (string) $validated['end_time']),
        ];
    }

    protected function assertTeam(Request $request, string $currentTeam, RoomBooking $booking): void
    {
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
        abort_unless($booking->team_id === $request->user()->currentTeam->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomPayload(MeetingRoom $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'color' => $room->color,
            'capacity' => $room->capacity,
            'location' => $room->location?->name,
            'timezone' => RoomAvailability::timezone($room),
            'opens_at' => $room->opens_at,
            'closes_at' => $room->closes_at,
            'is_active' => $room->is_active,
            'microsoft' => $room->isLinkedToMicrosoft(),
            'booking_url' => $room->publicBookingUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingPayload(RoomBooking $booking, User $user): array
    {
        $timezone = RoomAvailability::timezone($booking->room);
        $start = $booking->starts_at->setTimezone($timezone);
        $end = $booking->ends_at->setTimezone($timezone);

        return [
            'id' => $booking->id,
            'room_id' => $booking->meeting_room_id,
            'room_name' => $booking->room->name,
            'title' => $booking->title,
            'organizer_name' => $booking->organizer_name,
            'organizer_email' => $booking->organizer_email,
            'attendees' => $booking->attendees,
            'notes' => $booking->notes,
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'source' => $booking->source->value,
            'source_label' => $booking->source->label(),
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'microsoft' => filled($booking->external_id),
            'sync_error' => $booking->sync_error,
            'can_edit' => $user->can('update', $booking),
            'can_approve' => $user->can('approve', $booking),
        ];
    }
}
