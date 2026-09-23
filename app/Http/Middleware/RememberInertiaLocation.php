<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class RememberInertiaLocation
{
    /**
     * Record successful GET visits as the session previous URL.
     *
     * StartSession skips XHR, and Inertia sends X-Requested-With, so without this
     * back() keeps the full-page URL from login (the organization dashboard).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRemember($request, $response)) {
            $request->session()->setPreviousUrl($request->fullUrl());
        }

        return $response;
    }

    protected function shouldRemember(Request $request, Response $response): bool
    {
        return $request->hasSession()
            && $request->isMethod('GET')
            && $request->route() instanceof Route
            && ! $request->prefetch()
            && ! $request->isPrecognitive()
            && $response->getStatusCode() < 400;
    }
}
