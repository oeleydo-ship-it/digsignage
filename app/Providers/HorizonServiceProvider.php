<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

/**
 * Gates the Horizon dashboard to platform administrators.
 *
 * Horizon itself is optional infrastructure: it requires Redis
 * (QUEUE_CONNECTION=redis) and a Unix host (ext-pcntl / ext-posix), so it is
 * not installed in the local Windows dev environment. Install it in
 * production with:
 *
 *     composer require laravel/horizon
 *     php artisan horizon:install
 *
 * The package is auto-discovered; this provider becomes active as soon as
 * the classes exist and is a no-op otherwise.
 */
class HorizonServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! class_exists(Horizon::class)) {
            return;
        }

        Horizon::auth(function (?User $user): bool {
            return $user !== null && $user->is_platform_admin === true;
        });
    }
}
