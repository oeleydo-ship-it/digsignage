<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingGateway;
use App\Enums\PlanKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ApplyCouponRequest;
use App\Http\Requests\Billing\CheckoutBillingRequest;
use App\Http\Requests\Billing\SwapBillingRequest;
use App\Models\Invoice;
use App\Support\BillingCatalog;
use App\Support\TeamQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request, TeamQuota $quota): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $usage = $quota->usage($team);

        return Inertia::render('billing/index', [
            'driver' => config('billing.driver'),
            'subscription' => [
                'plan_key' => $team->plan_key->value,
                'plan_name' => $team->plan_key->label(),
                'status' => $team->subscription_status->value,
                'status_label' => $team->subscription_status->label(),
                'trial_ends_at' => $team->trial_ends_at?->toIso8601String(),
                'subscription_ends_at' => $team->subscription_ends_at?->toIso8601String(),
                'coupon_code' => $team->coupon_code,
                'allows_mutations' => $quota->allowsMutations($team),
            ],
            'usage' => $usage,
            'plans' => BillingCatalog::plans(),
            'featureOptions' => BillingCatalog::featureOptions(),
            'coupons' => array_keys(is_array(config('billing.coupons')) ? config('billing.coupons') : []),
            'invoices' => $team->invoices()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount_cents' => $invoice->amount_cents,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    'period_start' => $invoice->period_start?->toIso8601String(),
                    'period_end' => $invoice->period_end?->toIso8601String(),
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                    'created_at' => $invoice->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function checkout(CheckoutBillingRequest $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $plan = PlanKey::from($request->validated('plan_key'));
        $coupon = $request->validated('coupon_code');

        $url = $billing->checkout(
            $team,
            $plan,
            is_string($coupon) ? $coupon : null,
            route('billing.complete', $team).'?session_id={CHECKOUT_SESSION_ID}',
            route('billing.index', $team),
        );

        return redirect()->away($url);
    }

    public function complete(Request $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        if (config('billing.driver') === 'fake' && ! $request->hasValidSignature()) {
            abort(403);
        }

        $plan = PlanKey::tryFrom((string) $request->query('plan', ''));
        $coupon = $request->query('coupon');

        $billing->completeCheckout(
            $team,
            is_string($request->query('session_id')) ? $request->query('session_id') : null,
            $plan,
            is_string($coupon) && $coupon !== '' ? $coupon : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription updated.')]);

        return to_route('billing.index', $team);
    }

    public function swap(SwapBillingRequest $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $billing->swap($team, PlanKey::from($request->validated('plan_key')));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan changed. Existing screens and media were kept.')]);

        return to_route('billing.index', $team);
    }

    public function cancel(Request $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $billing->cancel($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription will end at the close of the current period.')]);

        return to_route('billing.index', $team);
    }

    public function resume(Request $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $billing->resume($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription resumed.')]);

        return to_route('billing.index', $team);
    }

    public function portal(Request $request, BillingGateway $billing): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        return redirect()->away($billing->portal($team, route('billing.index', $team)));
    }

    public function coupon(ApplyCouponRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team !== null, 403);
        Gate::authorize('manageBilling', $team);

        $code = $request->validated('coupon_code');

        if (! is_string($code) || BillingCatalog::coupon($code) === null) {
            return back()->withErrors(['coupon_code' => __('That coupon is not valid.')]);
        }

        $team->forceFill(['coupon_code' => strtoupper($code)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon saved. It will apply to the next invoice.')]);

        return to_route('billing.index', $team);
    }
}
