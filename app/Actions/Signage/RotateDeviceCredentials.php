<?php

namespace App\Actions\Signage;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RotateDeviceCredentials
{
    /**
     * Replace a screen's device bearer token. The plaintext is returned once.
     */
    public function handle(User $user, Screen $screen): string
    {
        if (! filled($screen->device_token_hash)) {
            throw ValidationException::withMessages([
                'screen' => __('Pair this screen before rotating credentials.'),
            ]);
        }

        $token = Str::password(64, symbols: false);

        $screen->forceFill([
            'device_token' => Hash::make($token),
            'device_token_hash' => DeviceToken::hash($token),
        ])->save();

        app(RecordOrganizationAudit::class)->handle(
            $screen->team,
            AuditAction::DeviceCredentialsRotated,
            $user,
            'screen',
            $screen->id,
            null,
            ['name' => $screen->name, 'device_uuid' => $screen->device_uuid],
        );

        return $token;
    }
}
