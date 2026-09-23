<?php

use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('player.{uuid}', function (?User $user, string $uuid): bool {
    if ($user?->currentTeam === null) {
        return false;
    }

    return Screen::query()
        ->forTeam($user->currentTeam)
        ->where('device_uuid', $uuid)
        ->exists();
});
