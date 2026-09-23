<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\AuthenticatePartnerApiToken;
use App\Http\Middleware\DenyWhileImpersonating;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureInitialAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogQueueApiRequest;
use App\Http\Middleware\RememberInertiaLocation;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\RequirePlanFeature;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'player.device' => AuthenticateDevice::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'impersonation.deny' => DenyWhileImpersonating::class,
            'partner.token' => AuthenticatePartnerApiToken::class,
            'partner.scope' => RequireApiScope::class,
            'queue.api.log' => LogQueueApiRequest::class,
            'plan.feature' => RequirePlanFeature::class,
        ]);

        $middleware->web(prepend: [EnsureInitialAdmin::class], append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            // Vite already renders preload tags; an unbounded Link header exceeds
            // common nginx FastCGI buffers and makes the login page return 502.
            SetTeamUrlDefaults::class,
            RememberInertiaLocation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
