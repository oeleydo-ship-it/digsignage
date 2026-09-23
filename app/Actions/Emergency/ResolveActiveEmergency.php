<?php

namespace App\Actions\Emergency;

use App\Enums\EmergencyStatus;
use App\Models\Emergency;
use App\Models\EmergencyDelivery;
use App\Models\Screen;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ResolveActiveEmergency
{
    public function handle(Screen $screen, ?DateTimeInterface $at = null): ?Emergency
    {
        $at = CarbonImmutable::parse($at ?? now());

        $emergencyIds = EmergencyDelivery::query()
            ->where('team_id', $screen->team_id)
            ->where('screen_id', $screen->id)
            ->pluck('emergency_id');

        if ($emergencyIds->isEmpty()) {
            return null;
        }

        $emergencies = Emergency::query()
            ->with(['image', 'video'])
            ->whereKey($emergencyIds)
            ->where('team_id', $screen->team_id)
            ->where('status', EmergencyStatus::Active)
            ->where(function ($query) use ($at) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            })
            ->get();

        return $emergencies
            ->sortByDesc(fn (Emergency $emergency) => sprintf(
                '%02d-%s',
                $emergency->severity->rank(),
                $emergency->started_at?->toIso8601String() ?? '0',
            ))
            ->first();
    }
}
