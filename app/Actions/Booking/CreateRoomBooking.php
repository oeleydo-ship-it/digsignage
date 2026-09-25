<?php

namespace App\Actions\Booking;

use App\Enums\RoomBookingNotice;
use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Enums\TeamPermission;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRoomBooking
{
    public function __construct(
        protected RoomBookingGuard $guard,
        protected RefreshRoomScreens $refreshScreens,
        protected NotifyRoomBooking $notify,
    ) {}

    /**
     * @param  array{title: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, organizer_name?: string|null, organizer_email?: string|null, attendees?: int|null, notes?: string|null}  $data
     */
    public function handle(MeetingRoom $room, array $data, RoomBookingSource $source, ?User $user = null): RoomBooking
    {
        $start = $data['starts_at'];
        $end = $data['ends_at'];
        $attendees = $data['attendees'] ?? null;
        // Booking managers may override opening hours, duration and capacity.
        $strict = $source === RoomBookingSource::PublicForm
            || $user === null
            || ! $user->hasTeamPermission($room->team, TeamPermission::ManageBookings);

        // Serialise bookings per room so two requests for the same slot
        // cannot both pass the conflict check.
        return Cache::lock('room-booking:'.$room->id, 15)->block(10, function () use ($room, $data, $source, $user, $start, $end, $attendees, $strict) {
            $this->guard->assertBookable($room, $start, $end, $attendees, $strict);

            $status = $source === RoomBookingSource::PublicForm && $room->requires_approval
                ? RoomBookingStatus::Pending
                : RoomBookingStatus::Confirmed;

            $booking = new RoomBooking([
                'team_id' => $room->team_id,
                'meeting_room_id' => $room->id,
                'title' => trim($data['title']),
                'organizer_name' => $data['organizer_name'] ?? $user?->name,
                'organizer_email' => $data['organizer_email'] ?? $user?->email,
                'attendees' => $attendees,
                'notes' => $data['notes'] ?? null,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => $status,
                'source' => $source,
                'created_by' => $user?->id,
            ]);

            if ($status === RoomBookingStatus::Confirmed) {
                $this->reserveInMicrosoft365($room, $booking);
            }

            DB::transaction(fn () => $booking->save());
            $this->refreshScreens->handle($room->team_id);

            if ($booking->status === RoomBookingStatus::Pending) {
                $this->notify->handle($booking, RoomBookingNotice::Requested);
                $this->notify->handle($booking, RoomBookingNotice::ApprovalNeeded);
            } else {
                $this->notify->handle($booking, RoomBookingNotice::Confirmed);
            }

            return $booking;
        });
    }

    /**
     * Write the booking to the room mailbox so it shows in Outlook and blocks
     * the room there too. Failing here keeps the two calendars from drifting.
     */
    public function reserveInMicrosoft365(MeetingRoom $room, RoomBooking $booking): void
    {
        $room->loadMissing('calendarConnection');

        if (! $room->isLinkedToMicrosoft() || filled($booking->external_id)) {
            return;
        }

        try {
            $event = MicrosoftGraphClient::for($room->calendarConnection)->createEvent(
                (string) $room->external_calendar_id,
                $booking->title,
                $booking->starts_at,
                $booking->ends_at,
                trim(implode("\n", array_filter([
                    $booking->organizer_name ? 'Booked by '.$booking->organizer_name : null,
                    $booking->notes,
                    'Booked through DigSignage',
                ]))),
                $booking->organizer_email,
                $booking->organizer_name,
            );
        } catch (MicrosoftGraphException $exception) {
            throw ValidationException::withMessages(['meeting_room_id' => $exception->getMessage()]);
        }

        $booking->external_id = $event['id'];
        $booking->external_change_key = is_string($event['change_key']) ? $event['change_key'] : null;
    }
}
