<?php

namespace App\Actions\Booking;

use App\Enums\RoomBookingNotice;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class UpdateRoomBooking
{
    public function __construct(
        protected RoomBookingGuard $guard,
        protected RefreshRoomScreens $refreshScreens,
        protected NotifyRoomBooking $notify,
    ) {}

    /**
     * Reschedule or edit a booking, optionally moving it to another room.
     *
     * @param  array{meeting_room_id: int, title: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, organizer_name?: string|null, organizer_email?: string|null, attendees?: int|null, notes?: string|null}  $data
     */
    public function handle(RoomBooking $booking, array $data, User $user): RoomBooking
    {
        if (! $booking->isActive()) {
            throw ValidationException::withMessages(['status' => __('Cancelled or declined bookings cannot be changed.')]);
        }

        $room = MeetingRoom::query()->where('team_id', $booking->team_id)->findOrFail($data['meeting_room_id']);
        $previousRoom = $booking->room;
        $strict = ! $user->can('approve', $booking);

        return Cache::lock('room-booking:'.$room->id, 15)->block(10, function () use ($booking, $data, $room, $previousRoom, $strict) {
            $sameRoom = $room->id === $previousRoom->id;

            $this->guard->assertBookable(
                $room,
                $data['starts_at'],
                $data['ends_at'],
                $data['attendees'] ?? null,
                $strict,
                $booking->id,
                $sameRoom ? $booking->external_id : null,
            );

            $booking->fill([
                'meeting_room_id' => $room->id,
                'title' => trim($data['title']),
                'organizer_name' => $data['organizer_name'] ?? $booking->organizer_name,
                'organizer_email' => $data['organizer_email'] ?? $booking->organizer_email,
                'attendees' => $data['attendees'] ?? null,
                'notes' => $data['notes'] ?? null,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);

            $this->syncToMicrosoft365($booking, $room, $previousRoom, $sameRoom);

            $booking->save();
            $this->refreshScreens->handle($booking->team_id);
            $this->notify->handle($booking, RoomBookingNotice::Rescheduled);

            return $booking->refresh();
        });
    }

    protected function syncToMicrosoft365(RoomBooking $booking, MeetingRoom $room, MeetingRoom $previousRoom, bool $sameRoom): void
    {
        $room->loadMissing('calendarConnection');
        $previousRoom->loadMissing('calendarConnection');

        try {
            if ($sameRoom && $room->isLinkedToMicrosoft() && filled($booking->external_id)) {
                $booking->external_change_key = MicrosoftGraphClient::for($room->calendarConnection)->updateEvent(
                    (string) $room->external_calendar_id,
                    (string) $booking->external_id,
                    $booking->title,
                    $booking->starts_at,
                    $booking->ends_at,
                );

                return;
            }

            if (! $sameRoom && filled($booking->external_id) && $previousRoom->isLinkedToMicrosoft()) {
                MicrosoftGraphClient::for($previousRoom->calendarConnection)
                    ->deleteEvent((string) $previousRoom->external_calendar_id, (string) $booking->external_id);
                $booking->external_id = null;
                $booking->external_change_key = null;
            }
        } catch (MicrosoftGraphException $exception) {
            throw ValidationException::withMessages(['meeting_room_id' => $exception->getMessage()]);
        }

        if (! $sameRoom) {
            app(CreateRoomBooking::class)->reserveInMicrosoft365($room, $booking);
        }
    }
}
