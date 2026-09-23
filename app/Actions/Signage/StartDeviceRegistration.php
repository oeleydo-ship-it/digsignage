<?php

namespace App\Actions\Signage;

use App\Models\DeviceRegistration;
use App\Support\RegistrationCode;
use Illuminate\Http\Request;

class StartDeviceRegistration
{
    /**
     * Create a pending pairing code for a player.
     *
     * @return array{registration: DeviceRegistration, code: string}
     */
    public function handle(Request $request): array
    {
        $attempts = 0;

        do {
            $code = RegistrationCode::generate();
            $hash = RegistrationCode::hash($code);
            $attempts++;
        } while (
            $attempts < 8
            && DeviceRegistration::query()->where('code_hash', $hash)->exists()
        );

        $registration = DeviceRegistration::query()->create([
            'code_hash' => $hash,
            'expires_at' => now()->addMinutes((int) config('signage.registration.ttl_minutes')),
            'player_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return [
            'registration' => $registration,
            'code' => $code,
        ];
    }
}
