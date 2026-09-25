<?php

namespace App\Actions\Booking;

use App\Enums\RoomBookingNotice;
use App\Enums\RoomBookingStatus;
use App\Models\RoomBooking;
use App\Services\Calendar\MicrosoftGraphClient;
use App\Services\Calendar\MicrosoftGraphException;
use Illuminate\Validation\ValidationException;

class CancelRoomBooking
{
    public function __construct(
        protected RefreshRoomScreens $refreshScreens,
        protected NotifyRoomBooking $notify,
    ) {}

    /**
     * Release the room. Pending requests become "declined", everything else
     * "cancelled", and the matching Microsoft 365 event is removed.
     */
    public function handle(RoomBooking $booking, RoomBookingStatus $status = RoomBookingStatus::Cancelled): RoomBooking
    {
        if (! $booking->isActive()) {
            return $booking;
        }

        $room = $booking->room()->with('calendarConnection')->firstOrFail();

        if (filled($booking->external_id) && $room->isLinkedToMicrosoft()) {
            try {
                MicrosoftGraphClient::for($room->calendarConnection)
                    ->deleteEvent((string) $room->external_calendar_id, (string) $booking->external_id);
            } catch (MicrosoftGraphException $exception) {
                throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
            }
        }

        $booking->forceFill([
            'status' => $status,
            'cancelled_at' => now(),
            'sync_error' => null,
        ])->save();

        $this->refreshScreens->handle($booking->team_id);
        $this->notify->handle(
            $booking,
            $status === RoomBookingStatus::Declined ? RoomBookingNotice::Declined : RoomBookingNotice::Cancelled,
        );

        return $booking;
    }
}
