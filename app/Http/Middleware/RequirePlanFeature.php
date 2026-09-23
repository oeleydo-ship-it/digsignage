<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Models\Team;
use App\Support\TeamQuota;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanFeature
{
    public function __construct(protected TeamQuota $quota) {}

    /**
     * Require a plan entitlement without coupling access to a plan name.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $entitlement = PlanFeature::tryFrom($feature);
        abort_if($entitlement === null, 500, 'Unknown plan feature.');

        $team = $this->resolveTeam($request);
        abort_if($team === null, 403);

        if (! $this->quota->allowsFeature($team, $entitlement)) {
            $message = __(':feature is not included in this plan.', [
                'feature' => $entitlement->label(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }

    protected function resolveTeam(Request $request): ?Team
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Team) {
                return $parameter;
            }

            if ($parameter instanceof Model && $parameter->getAttribute('team_id')) {
                return Team::query()
                    ->whereKey($parameter->getAttribute('team_id'))
                    ->first();
            }
        }

        return $request->user()?->currentTeam;
    }
}
