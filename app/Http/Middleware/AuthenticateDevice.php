<?php

namespace App\Http\Middleware;

use App\Models\Screen;
use App\Support\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Authenticate a paired player using its device bearer token.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        $screen = Screen::query()
            ->where('device_token_hash', DeviceToken::hash($token))
            ->first();

        if ($screen === null) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        $request->attributes->set('playerScreen', $screen);

        return $next($request);
    }
}
