<?php

namespace App\Actions\Signage;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\ScreenStatus;
use App\Models\DeviceRegistration;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use App\Support\DeviceToken;
use App\Support\RegistrationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PairDeviceRegistration
{
    /**
     * Claim a pending player code and create a paired screen.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{screen: Screen, device_token: string}
     */
    public function handle(User $user, Team $team, string $code, array $attributes): array
    {
        return DB::transaction(function () use ($user, $team, $code, $attributes) {
            $registration = DeviceRegistration::query()
                ->where('code_hash', RegistrationCode::hash($code))
                ->lockForUpdate()
                ->first();

            if (! $registration || ! $registration->isPending()) {
                throw ValidationException::withMessages([
                    'code' => __('This registration code is invalid, expired, or already used.'),
                ]);
            }

            if ($registration->team_id !== null && $registration->team_id !== $team->id) {
                throw ValidationException::withMessages([
                    'code' => __('This registration code is invalid, expired, or already used.'),
                ]);
            }

            $token = Str::password(64, symbols: false);
            $uuid = (string) Str::uuid();

            $screen = app(SaveScreen::class)->handle($team, $attributes);
            $screen->forceFill([
                'device_uuid' => $uuid,
                'device_token' => Hash::make($token),
                'device_token_hash' => DeviceToken::hash($token),
                'status' => ScreenStatus::Offline,
            ])->save();

            $registration->forceFill([
                'team_id' => $team->id,
                'screen_id' => $screen->id,
                'claimed_by_user_id' => $user->id,
                'consumed_at' => now(),
            ])->save();

            Cache::put(
                $this->cacheKey($registration),
                $token,
                now()->addMinutes((int) config('signage.registration.token_ttl_minutes')),
            );

            app(RecordOrganizationAudit::class)->handle(
                $team,
                AuditAction::ScreenRegistered,
                $user,
                'screen',
                $screen->id,
                null,
                ['name' => $screen->name, 'device_uuid' => $uuid],
            );

            return ['screen' => $screen->refresh(), 'device_token' => $token];
        });
    }

    /**
     * Pull the one-time device token for the player.
     */
    public function pullDeviceToken(DeviceRegistration $registration): ?string
    {
        $token = Cache::pull($this->cacheKey($registration));

        return is_string($token) ? $token : null;
    }

    /**
     * Cache key for the one-time device credential.
     */
    protected function cacheKey(DeviceRegistration $registration): string
    {
        return 'device-registration-token:'.$registration->id;
    }
}
