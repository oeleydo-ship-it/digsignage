<?php

namespace App\Data;

readonly class BookingPermissions
{
    public function __construct(
        public bool $canViewBookings,
        public bool $canCreateBooking,
        public bool $canManageBookings,
        public bool $canManageRooms,
    ) {
        //
    }
}
