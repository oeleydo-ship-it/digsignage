<?php

namespace App\Billing;

use App\Enums\PlanKey;
use App\Models\Team;

interface BillingGateway
{
    public function checkout(Team $team, PlanKey $plan, ?string $coupon, string $successUrl, string $cancelUrl): string;

    public function completeCheckout(Team $team, ?string $sessionId = null, ?PlanKey $plan = null, ?string $coupon = null): void;

    public function swap(Team $team, PlanKey $plan): void;

    public function cancel(Team $team): void;

    public function resume(Team $team): void;

    public function portal(Team $team, string $returnUrl): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, ?string $signatureHeader, ?string $rawPayload = null): void;
}
