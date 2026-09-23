<?php

namespace App\Support;

use App\Models\User;

class InitialAdminSetup
{
    public function required(): bool
    {
        return config('app.env') === 'production'
            && ! User::query()->where('is_platform_admin', true)->exists();
    }

    public function configured(): bool
    {
        return strlen((string) config('setup.initial_admin_key')) >= 32;
    }
}
