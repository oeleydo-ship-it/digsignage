<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\RefreshRoomScreens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\SaveMeetingRoomRequest;
use App\Models\CalendarConnection;
use App\Models\Location;
use App\Models\MeetingRoom;
use App\Models\Team;
use App\Support\RoomAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingRoomController extends Controller
{
    public function __construct(protected RefreshRoomScreens $refreshScreens) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MeetingRoom::class);

        $team = $request->user()->currentTeam;
        $connection = $team->calendarConnections()->where('provider', CalendarConnection::MICROSOFT_365)->first();

        return Inertia::render('bookings/rooms', [
            'rooms' => MeetingRoom::query()
                ->forTeam($team)
                ->with(['location.parent', 'calendarConnection'])
                ->withCount(['bookings as upcoming_count' => fn ($query) => $query->blocking()->where('ends_at', '>', now())])
                ->orderBy('name')
                ->get()
                ->map(fn (MeetingRoom $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->description,
                    'location_id' => $room->location_id,
                    'location' => $room->location?->name,
                    'capacity' => $room->capacity,
                    'amenities' => $room->amenities ?? [],
                    'color' => $room->color,
                    'is_active' => $room->is_active,
                    'public_booking_enabled' => $room->public_booking_enabled,
                    'requires_approval' => $room->requires_approval,
                    'min_duration_minutes' => $room->min_duration_minutes,
                    'max_duration_minutes' => $room->max_duration_minutes,
                    'opens_at' => $room->opens_at,
                    'closes_at' => $room->closes_at,
                    'timezone' => RoomAvailability::timezone($room),
                    'external_calendar_id' => $room->external_calendar_id,
                    'microsoft' => $room->isLinkedToMicrosoft(),
                    'booking_url' => $room->publicBookingUrl(),
                    'upcoming_count' => (int) $room->getAttribute('upcoming_count'),
                ]),
            'locations' => Location::query()
                ->forTeam($team)
                ->orderBy('path')
                ->orderBy('name')
                ->get(['id', 'name', 'depth'])
                ->map(fn (Location $location) => ['id' => $location->id, 'name' => $location->name, 'depth' => $location->depth]),
            'microsoftConnected' => $connection !== null && $connection->is_active && $connection->isConfigured(),
            'permissions' => $request->user()->toBookingPermissions($team),
        ]);
    }

    public function store(SaveMeetingRoomRequest $request): RedirectResponse
    {
        Gate::authorize('create', MeetingRoom::class);

        $team = $request->user()->currentTeam;
        MeetingRoom::query()->create([
            ...$this->attributes($request, $team),
            'team_id' => $team->id,
        ]);

        $this->refreshScreens->handle($team->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room added.')]);

        return back();
    }

    public function update(SaveMeetingRoomRequest $request, string $current_team, MeetingRoom $meetingRoom): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $meetingRoom);
        Gate::authorize('update', $meetingRoom);

        $meetingRoom->update($this->attributes($request, $request->user()->currentTeam));

        $this->refreshScreens->handle($meetingRoom->team_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room updated.')]);

        return back();
    }

    public function destroy(Request $request, string $current_team, MeetingRoom $meetingRoom): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $meetingRoom);
        Gate::authorize('delete', $meetingRoom);

        $teamId = $meetingRoom->team_id;
        $meetingRoom->delete();

        $this->refreshScreens->handle($teamId);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room deleted.')]);

        return back();
    }

    /**
     * Issue a new public booking link, invalidating any printed QR codes.
     */
    public function rotateLink(Request $request, string $current_team, MeetingRoom $meetingRoom): RedirectResponse
    {
        $this->assertTeam($request, $current_team, $meetingRoom);
        Gate::authorize('update', $meetingRoom);

        $meetingRoom->forceFill(['booking_token' => MeetingRoom::newBookingToken()])->save();

        $this->refreshScreens->handle($meetingRoom->team_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('New booking link created. Old links and QR codes no longer work.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(SaveMeetingRoomRequest $request, Team $team): array
    {
        $validated = $request->validated();
        $mailbox = $validated['external_calendar_id'] ?? null;
        $connection = $mailbox !== null
            ? $team->calendarConnections()->where('provider', CalendarConnection::MICROSOFT_365)->first()
            : null;

        return [
            ...collect($validated)->except(['external_calendar_id'])->all(),
            'external_calendar_id' => $mailbox !== null ? strtolower($mailbox) : null,
            'calendar_connection_id' => $connection?->id,
        ];
    }

    protected function assertTeam(Request $request, string $currentTeam, MeetingRoom $room): void
    {
        abort_unless($currentTeam === $request->user()->currentTeam->slug, 403);
        abort_unless($room->team_id === $request->user()->currentTeam->id, 404);
    }
}
