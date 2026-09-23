<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_platform_admin === true, 403);

        abort_if(
            Impersonation::active($request),
            403,
            __('Exit impersonation before using platform administration.'),
        );

        return $next($request);
    }
}
