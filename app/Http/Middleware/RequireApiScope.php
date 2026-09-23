<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->attributes->get('apiToken');

        if (! $token instanceof ApiToken || ! $token->allows($scope)) {
            return response()->json([
                'message' => 'This token is missing the '.$scope.' scope.',
            ], 403);
        }

        return $next($request);
    }
}
