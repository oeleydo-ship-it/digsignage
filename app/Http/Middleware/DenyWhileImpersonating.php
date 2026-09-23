<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyWhileImpersonating
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            Impersonation::active($request),
            403,
            __('This action is blocked while impersonating a user.'),
        );

        return $next($request);
    }
}
