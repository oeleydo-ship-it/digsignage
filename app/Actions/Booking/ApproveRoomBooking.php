<?php

namespace App\Actions\Booking;

use App\Enums\RoomBookingNotice;
use App\Enums\RoomBookingStatus;
use App\Models\RoomBooking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ApproveRoomBooking
{
    public function __construct(
        protected RoomBookingGuard $guard,
        protected CreateRoomBooking $createBooking,
        protected RefreshRoomScreens $refreshScreens,
        protected NotifyRoomBooking $notify,
    ) {}

    /**
     * Confirm a request from the public booking form. The slot is checked
     * again because the calendar may have changed since it was requested.
     */
    public function handle(RoomBooking $booking): RoomBooking
    {
        if ($booking->status !== RoomBookingStatus::Pending) {
            throw ValidationException::withMessages(['status' => __('Only pending requests can be approved.')]);
        }

        $room = $booking->room()->with('calendarConnection')->firstOrFail();

        return Cache::lock('room-booking:'.$room->id, 15)->block(10, function () use ($booking, $room) {
            $this->guard->assertBookable(
                $room,
                $booking->starts_at->toImmutable(),
                $booking->ends_at->toImmutable(),
                strict: false,
                ignoreBookingId: $booking->id,
            );

            $booking->status = RoomBookingStatus::Confirmed;
            $this->createBooking->reserveInMicrosoft365($room, $booking);
            $booking->save();

            $this->refreshScreens->handle($booking->team_id);
            $this->notify->handle($booking, RoomBookingNotice::Confirmed);

            return $booking;
        });
    }
}
