<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\RefreshRoomScreens;
use App\Actions\Booking\SyncMicrosoftCalendars;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\SaveCalendarConnectionRequest;
use App\Models\CalendarConnection;
use App\Models\MeetingRoom;
use App\Models\Team;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MicrosoftCalendarController extends Controller
{
    public function show(Request $request): Response
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        return Inertia::render('bookings/microsoft', [
            'connection' => $connection === null ? null : [
                'tenant_id' => $connection->tenant_id,
                'client_id' => $connection->client_id,
                'has_secret' => filled($connection->client_secret),
                'is_active' => $connection->is_active,
                'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                'last_error' => $connection->last_error,
            ],
            'linkedRooms' => MeetingRoom::query()
                ->forTeam($team)
                ->whereNotNull('external_calendar_id')
                ->orderBy('name')
                ->get(['id', 'name', 'external_calendar_id'])
                ->map(fn (MeetingRoom $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'email' => $room->external_calendar_id,
                ]),
            'rooms' => MeetingRoom::query()
                ->forTeam($team)
                ->orderBy('name')
                ->get(['id', 'name', 'external_calendar_id'])
                ->map(fn (MeetingRoom $room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'email' => $room->external_calendar_id,
                ]),
            'syncMinutes' => 5,
        ]);
    }

    public function update(SaveCalendarConnectionRequest $request): RedirectResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team) ?? new CalendarConnection([
            'team_id' => $team->id,
            'provider' => CalendarConnection::MICROSOFT_365,
        ]);

        $connection->fill([
            'tenant_id' => trim((string) $request->validated('tenant_id')),
            'client_id' => trim((string) $request->validated('client_id')),
            'is_active' => $request->boolean('is_active'),
        ]);

        if (filled($request->validated('client_secret'))) {
            $connection->client_secret = (string) $request->validated('client_secret');
        }

        $connection->last_error = null;
        $connection->save();

        // Rooms that name a mailbox but predate the connection pick it up now.
        MeetingRoom::query()
            ->forTeam($team)
            ->whereNotNull('external_calendar_id')
            ->whereNull('calendar_connection_id')
            ->update(['calendar_connection_id' => $connection->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Microsoft 365 settings saved.')]);

        return back();
    }

    /**
     * Sign in with the saved credentials and list rooms to prove access.
     */
    public function test(Request $request): RedirectResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        abort_if($connection === null, 404);

        try {
            $rooms = MicrosoftGraphClient::for($connection)->rooms();
        } catch (MicrosoftGraphException $exception) {
            $connection->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $connection->forceFill(['last_error' => null])->save();
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Connected. Found :count room mailbox(es) in Microsoft 365.', ['count' => count($rooms)]),
        ]);

        return back();
    }

    /**
     * Room mailboxes in the tenant, for the import picker.
     */
    public function rooms(Request $request): JsonResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        if ($connection === null || ! $connection->isConfigured()) {
            return response()->json(['message' => __('Connect Microsoft 365 first.')], 422);
        }

        try {
            $rooms = MicrosoftGraphClient::for($connection)->rooms();
        } catch (MicrosoftGraphException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $linked = MeetingRoom::query()
            ->forTeam($team)
            ->whereNotNull('external_calendar_id')
            ->pluck('name', 'external_calendar_id');

        return response()->json([
            'rooms' => array_map(fn (array $room) => [
                ...$room,
                'linked_to' => $linked[strtolower($room['email'])] ?? null,
            ], $rooms),
        ]);
    }

    /**
     * Create DigSignage rooms for selected Microsoft 365 room mailboxes, or
     * link a mailbox to an existing room.
     */
    public function import(Request $request, SyncMicrosoftCalendars $sync, RefreshRoomScreens $refreshScreens): RedirectResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        abort_if($connection === null, 404);

        $validated = $request->validate([
            'rooms' => ['required', 'array', 'min:1', 'max:200'],
            'rooms.*.email' => ['required', 'email', 'max:255'],
            'rooms.*.name' => ['required', 'string', 'max:120'],
            'rooms.*.capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'rooms.*.meeting_room_id' => ['nullable', 'integer', Rule::exists('meeting_rooms', 'id')->where('team_id', $team->id)],
        ]);

        DB::transaction(function () use ($validated, $team, $connection): void {
            foreach ($validated['rooms'] as $row) {
                $email = strtolower($row['email']);

                // Unlink the mailbox from any other room first: one mailbox, one room.
                MeetingRoom::query()->forTeam($team)->where('external_calendar_id', $email)->update([
                    'external_calendar_id' => null,
                    'calendar_connection_id' => null,
                ]);

                if (! empty($row['meeting_room_id'])) {
                    MeetingRoom::query()->forTeam($team)->whereKey($row['meeting_room_id'])->update([
                        'external_calendar_id' => $email,
                        'calendar_connection_id' => $connection->id,
                    ]);

                    continue;
                }

                MeetingRoom::query()->create([
                    'team_id' => $team->id,
                    'name' => $row['name'],
                    'capacity' => $row['capacity'] ?? null,
                    'external_calendar_id' => $email,
                    'calendar_connection_id' => $connection->id,
                ]);
            }
        });

        $message = __('Rooms linked to Microsoft 365.');

        try {
            $result = $sync->handle($connection);
            $message .= ' '.__('Imported :count meeting(s).', ['count' => $result['created']]);
        } catch (MicrosoftGraphException $exception) {
            $message .= ' '.__('The first sync failed: :error', ['error' => $exception->getMessage()]);
        }

        $refreshScreens->handle($team->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function sync(Request $request, SyncMicrosoftCalendars $sync): RedirectResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        abort_if($connection === null, 404);

        try {
            $result = $sync->handle($connection);
        } catch (MicrosoftGraphException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Synced :rooms room(s): :created new, :updated changed, :cancelled cancelled.', $result)]);

        return back();
    }

    /**
     * Forget the credentials. Rooms keep their mailbox address so they can be
     * relinked; mirrored Outlook bookings stay until they pass.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = $this->authorizeTeam($request);
        $connection = $this->connection($team);

        if ($connection !== null) {
            MeetingRoom::query()->where('calendar_connection_id', $connection->id)->update(['calendar_connection_id' => null]);
            $connection->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Microsoft 365 disconnected.')]);

        return back();
    }

    protected function authorizeTeam(Request $request): Team
    {
        $team = $request->user()->currentTeam;

        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageRooms), 403);

        return $team;
    }

    protected function connection(Team $team): ?CalendarConnection
    {
        return $team->calendarConnections()->where('provider', CalendarConnection::MICROSOFT_365)->first();
    }
}
