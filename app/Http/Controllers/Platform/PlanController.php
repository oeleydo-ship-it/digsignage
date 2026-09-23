<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SavePlanRequest;
use App\Models\Plan;
use App\Models\Team;
use App\Support\BillingCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $counts = Team::query()
            ->toBase()
            ->selectRaw('plan_key, count(*) as aggregate')
            ->groupBy('plan_key')
            ->pluck('aggregate', 'plan_key');

        return Inertia::render('platform/plans/index', [
            'plans' => collect(BillingCatalog::managedPlans())->map(fn (array $plan) => [
                ...$plan,
                'price_usd' => $plan['price_cents'] === null ? null : round($plan['price_cents'] / 100, 2),
                'organizations' => (int) ($counts[$plan['key']] ?? 0),
            ]),
            'features' => BillingCatalog::featureOptions(),
            'currency' => strtoupper((string) config('billing.currency', 'usd')),
        ]);
    }

    public function store(SavePlanRequest $request, RecordPlatformAudit $audit): RedirectResponse
    {
        $features = BillingCatalog::featuresFromRequest($request->input('features'));

        $plan = Plan::query()->create([
            'key' => $request->validated('key'),
            'name' => $request->validated('name'),
            'screens' => $request->validated('screens'),
            'storage_gb' => $request->validated('storage_gb'),
            'users' => $request->validated('users'),
            'bandwidth_gb' => $request->validated('bandwidth_gb'),
            'price_cents' => $request->validated('price_cents'),
            'features' => $features,
        ]);

        $audit->handle(
            PlatformAuditAction::PlanUpdated,
            $request->user(),
            'plan',
            null,
            null,
            [
                'key' => $plan->key,
                'name' => $plan->name,
                'screens' => $plan->screens,
                'features' => $features,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan created.')]);

        return back();
    }

    public function update(SavePlanRequest $request, Plan $plan, RecordPlatformAudit $audit): RedirectResponse
    {
        $before = $plan->only(['name', 'screens', 'storage_gb', 'users', 'bandwidth_gb', 'price_cents', 'features']);
        $features = BillingCatalog::featuresFromRequest($request->input('features'));

        $plan->forceFill([
            'name' => $request->validated('name'),
            'screens' => $request->validated('screens'),
            'storage_gb' => $request->validated('storage_gb'),
            'users' => $request->validated('users'),
            'bandwidth_gb' => $request->validated('bandwidth_gb'),
            'price_cents' => $request->validated('price_cents'),
            'features' => $features,
        ])->save();

        $audit->handle(
            PlatformAuditAction::PlanUpdated,
            $request->user(),
            'plan',
            null,
            $before,
            [
                'key' => $plan->key,
                'name' => $plan->name,
                'screens' => $plan->screens,
                'features' => $features,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan quotas and feature gates saved.')]);

        return back();
    }
}
