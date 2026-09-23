<?php

namespace App\Billing;

use App\Actions\Billing\ApplyTeamSubscription;
use App\Actions\Billing\RecordInvoice;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use App\Support\BillingCatalog;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class FakeBillingGateway implements BillingGateway
{
    public function __construct(
        protected ApplyTeamSubscription $apply,
        protected RecordInvoice $invoices,
    ) {}

    public function checkout(Team $team, PlanKey $plan, ?string $coupon, string $successUrl, string $cancelUrl): string
    {
        $this->assertCoupon($coupon);

        return URL::temporarySignedRoute('billing.complete', now()->addMinutes(30), [
            'current_team' => $team->slug,
            'plan' => $plan->value,
            'coupon' => $coupon ?? '',
        ]);
    }

    public function completeCheckout(Team $team, ?string $sessionId = null, ?PlanKey $plan = null, ?string $coupon = null): void
    {
        $plan ??= $team->plan_key ?? PlanKey::Starter;
        $this->assertCoupon($coupon);

        $catalog = BillingCatalog::plan($plan);

        $this->apply->handle(
            $team,
            $plan,
            SubscriptionStatus::Active,
            [
                'customer_id' => $team->stripe_customer_id ?: 'cus_fake_'.$team->id,
                'subscription_id' => 'sub_fake_'.$team->id,
                'price_id' => $catalog['stripe_price_id'],
            ],
            $coupon,
            null,
            null,
        );

        if (($catalog['price_cents'] ?? 0) > 0) {
            $this->invoices->handle($team, [
                'amount_cents' => $catalog['price_cents'],
                'coupon_code' => $coupon,
                'status' => 'paid',
            ]);
        }
    }

    public function swap(Team $team, PlanKey $plan): void
    {
        $catalog = BillingCatalog::plan($plan);
        $previous = $team->plan_key ?? PlanKey::Starter;

        $this->apply->handle(
            $team,
            $plan,
            $team->subscription_status === SubscriptionStatus::Trialing
                ? SubscriptionStatus::Active
                : ($team->subscription_status ?? SubscriptionStatus::Active),
            ['price_id' => $catalog['stripe_price_id']],
            $team->coupon_code,
            null,
            $team->subscription_ends_at,
        );

        if (($catalog['price_cents'] ?? 0) > 0 && $plan->rank() >= $previous->rank()) {
            $this->invoices->handle($team, [
                'amount_cents' => $catalog['price_cents'],
                'coupon_code' => $team->coupon_code,
                'status' => 'paid',
            ]);
        }
    }

    public function cancel(Team $team): void
    {
        $ends = now()->addMonth();

        $this->apply->handle(
            $team,
            $team->plan_key ?? PlanKey::Starter,
            SubscriptionStatus::Canceled,
            [],
            $team->coupon_code,
            $team->trial_ends_at,
            $ends,
        );
    }

    public function resume(Team $team): void
    {
        if ($team->subscription_status !== SubscriptionStatus::Canceled) {
            return;
        }

        $this->apply->handle(
            $team,
            $team->plan_key ?? PlanKey::Starter,
            SubscriptionStatus::Active,
            [],
            $team->coupon_code,
            null,
            null,
        );
    }

    public function portal(Team $team, string $returnUrl): string
    {
        return $returnUrl;
    }

    public function handleWebhook(array $payload, ?string $signatureHeader, ?string $rawPayload = null): void
    {
        //
    }

    protected function assertCoupon(?string $coupon): void
    {
        if ($coupon === null || $coupon === '') {
            return;
        }

        if (BillingCatalog::coupon($coupon) === null) {
            throw ValidationException::withMessages([
                'coupon_code' => __('That coupon is not valid.'),
            ]);
        }
    }
}
