<?php

namespace App\Actions\Billing;

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use App\Support\BillingCatalog;

class ApplyTeamSubscription
{
    /**
     * @param  array<string, mixed>  $stripe
     */
    public function handle(
        Team $team,
        PlanKey $plan,
        SubscriptionStatus $status,
        array $stripe = [],
        ?string $coupon = null,
        ?\DateTimeInterface $trialEndsAt = null,
        ?\DateTimeInterface $subscriptionEndsAt = null,
    ): void {
        $catalog = BillingCatalog::plan($plan);

        $team->forceFill([
            'plan_key' => $plan,
            'subscription_status' => $status,
            'coupon_code' => $coupon !== null && $coupon !== '' ? strtoupper($coupon) : $team->coupon_code,
            'stripe_customer_id' => $stripe['customer_id'] ?? $team->stripe_customer_id,
            'stripe_subscription_id' => $stripe['subscription_id'] ?? $team->stripe_subscription_id,
            'stripe_price_id' => $stripe['price_id'] ?? $catalog['stripe_price_id'] ?? $team->stripe_price_id,
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => $subscriptionEndsAt,
        ])->save();
    }
}
