<?php

namespace App\Enums;

enum RoomBookingNotice: string
{
    case Confirmed = 'confirmed';
    case Requested = 'requested';
    case ApprovalNeeded = 'approval_needed';
    case Rescheduled = 'rescheduled';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * Whether the email carries a calendar attachment.
     */
    public function hasCalendarFile(): bool
    {
        return in_array($this, [self::Confirmed, self::Rescheduled, self::Cancelled], true);
    }
}
