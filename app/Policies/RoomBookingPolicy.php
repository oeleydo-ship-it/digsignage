<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\RoomBooking;
use App\Models\User;

class RoomBookingPolicy
{
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewBookings);
    }

    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::CreateBooking);
    }

    /**
     * Managers can change any booking; everyone else only their own.
     */
    public function update(User $user, RoomBooking $booking): bool
    {
        if ($user->currentTeam?->id !== $booking->team_id) {
            return false;
        }

        if ($user->hasTeamPermission($booking->team, TeamPermission::ManageBookings)) {
            return true;
        }

        return $booking->created_by === $user->id
            && $user->hasTeamPermission($booking->team, TeamPermission::CreateBooking);
    }

    public function cancel(User $user, RoomBooking $booking): bool
    {
        return $this->update($user, $booking);
    }

    /**
     * Approving or declining a request from the public booking form.
     */
    public function approve(User $user, RoomBooking $booking): bool
    {
        return $user->currentTeam?->id === $booking->team_id
            && $user->hasTeamPermission($booking->team, TeamPermission::ManageBookings);
    }
}
