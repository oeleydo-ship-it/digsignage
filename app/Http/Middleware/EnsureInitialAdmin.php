<?php

namespace App\Http\Middleware;

use App\Support\InitialAdminSetup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInitialAdmin
{
    public function __construct(private InitialAdminSetup $setup) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->setup->required() || $request->is('register')) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->route('register');
        }

        abort(503, 'Configure the initial platform administrator before using this site.');
    }
}
