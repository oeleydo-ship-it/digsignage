<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\TeamQuota;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartnerApiToken
{
    /**
     * Authenticate a versioned partner API request from a bearer token.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::query()
            ->with(['user', 'team'])
            ->where('token_hash', ApiToken::hashToken($plain))
            ->first();

        if ($token === null || $token->hasExpired()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $token->user;
        $team = $token->team;

        if ($team->suspended_at !== null) {
            return response()->json(['message' => 'This organization is suspended.'], 403);
        }

        if (! app(TeamQuota::class)->allowsFeature($team, PlanFeature::PartnerApi)) {
            return response()->json(['message' => 'Partner API is not included in this plan.'], 403);
        }

        $user->setRelation('currentTeam', $team);
        $user->current_team_id = $team->id;

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('apiToken', $token);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
