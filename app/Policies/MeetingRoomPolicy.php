<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\MeetingRoom;
use App\Models\User;

class MeetingRoomPolicy
{
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ViewBookings);
    }

    public function view(User $user, MeetingRoom $room): bool
    {
        return $user->currentTeam?->id === $room->team_id
            && $user->hasTeamPermission($room->team, TeamPermission::ViewBookings);
    }

    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasTeamPermission($team, TeamPermission::ManageRooms);
    }

    public function update(User $user, MeetingRoom $room): bool
    {
        return $user->currentTeam?->id === $room->team_id
            && $user->hasTeamPermission($room->team, TeamPermission::ManageRooms);
    }

    public function delete(User $user, MeetingRoom $room): bool
    {
        return $this->update($user, $room);
    }
}
