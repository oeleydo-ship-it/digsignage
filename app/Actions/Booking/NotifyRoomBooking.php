<?php

namespace App\Actions\Booking;

use App\Enums\RoomBookingNotice;
use App\Enums\RoomBookingSource;
use App\Enums\TeamPermission;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\RoomBookingMail;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the emails that go with a booking changing state.
 */
class NotifyRoomBooking
{
    public function handle(RoomBooking $booking, RoomBookingNotice $notice): void
    {
        // Outlook already tells people about meetings booked there.
        if ($booking->source === RoomBookingSource::Microsoft365) {
            return;
        }

        if ($notice === RoomBookingNotice::ApprovalNeeded) {
            $this->notifyManagers($booking);

            return;
        }

        $email = trim((string) $booking->organizer_email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        Notification::route('mail', [$email => $booking->organizer_name ?: $email])
            ->notify(new RoomBookingMail($booking, $notice));
    }

    /**
     * Email everyone who can approve requests for this organization.
     */
    protected function notifyManagers(RoomBooking $booking): void
    {
        $team = $booking->team()->firstOrFail();

        $managers = $team->members()
            ->get()
            ->filter(fn (User $user) => $user->hasTeamPermission($team, TeamPermission::ManageBookings));

        if ($managers->isNotEmpty()) {
            Notification::send($managers, new RoomBookingMail($booking, RoomBookingNotice::ApprovalNeeded));
        }
    }
}
