<?php

namespace App\Billing;

use App\Actions\Billing\ApplyTeamSubscription;
use App\Actions\Billing\RecordInvoice;
use App\Actions\Notifications\DispatchSignageAlert;
use App\Enums\PlanKey;
use App\Enums\SignageAlert;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\BillingCatalog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StripeBillingGateway implements BillingGateway
{
    public function __construct(
        protected ApplyTeamSubscription $apply,
        protected RecordInvoice $invoices,
        protected DispatchSignageAlert $alerts,
    ) {}

    public function checkout(Team $team, PlanKey $plan, ?string $coupon, string $successUrl, string $cancelUrl): string
    {
        $catalog = BillingCatalog::plan($plan);

        if ($catalog['stripe_price_id'] === null) {
            throw ValidationException::withMessages([
                'plan_key' => __('Enterprise billing is arranged with sales. Choose Starter or Business to checkout online.'),
            ]);
        }

        $customerId = $this->customerId($team);
        $payload = [
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $team->id,
            'metadata' => [
                'team_id' => (string) $team->id,
                'plan_key' => $plan->value,
            ],
            'subscription_data' => [
                'metadata' => [
                    'team_id' => (string) $team->id,
                    'plan_key' => $plan->value,
                ],
            ],
            'line_items' => [
                ['price' => $catalog['stripe_price_id'], 'quantity' => 1],
            ],
        ];

        $promo = BillingCatalog::coupon($coupon)['stripe_promotion_code'] ?? null;

        if (is_string($promo) && $promo !== '') {
            $payload['discounts'] = [['promotion_code' => $promo]];
        } elseif (is_string($coupon) && $coupon !== '' && BillingCatalog::coupon($coupon) === null) {
            throw ValidationException::withMessages([
                'coupon_code' => __('That coupon is not valid.'),
            ]);
        }

        if ($team->subscription_status === SubscriptionStatus::Trialing && $team->trial_ends_at?->isFuture()) {
            $payload['subscription_data']['trial_end'] = $team->trial_ends_at->getTimestamp();
        }

        $session = $this->request('post', '/v1/checkout/sessions', $payload);

        $url = $session['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe checkout did not return a session URL.');
        }

        $team->forceFill([
            'stripe_customer_id' => $customerId,
            'coupon_code' => is_string($coupon) && $coupon !== '' ? strtoupper($coupon) : $team->coupon_code,
        ])->save();

        return $url;
    }

    public function completeCheckout(Team $team, ?string $sessionId = null, ?PlanKey $plan = null, ?string $coupon = null): void
    {
        if ($sessionId === null || $sessionId === '') {
            return;
        }

        $session = $this->request('get', '/v1/checkout/sessions/'.$sessionId, [
            'expand' => ['subscription'],
        ]);

        $this->applySession($team, $session);
    }

    public function swap(Team $team, PlanKey $plan): void
    {
        $catalog = BillingCatalog::plan($plan);

        if ($catalog['stripe_price_id'] === null || ! is_string($team->stripe_subscription_id) || $team->stripe_subscription_id === '') {
            $this->apply->handle($team, $plan, $team->subscription_status ?? SubscriptionStatus::Active);

            return;
        }

        $subscription = $this->request('get', '/v1/subscriptions/'.$team->stripe_subscription_id);
        $itemId = $subscription['items']['data'][0]['id'] ?? null;

        if (! is_string($itemId)) {
            throw new RuntimeException('Stripe subscription is missing a price item.');
        }

        $updated = $this->request('post', '/v1/subscriptions/'.$team->stripe_subscription_id, [
            'items' => [
                ['id' => $itemId, 'price' => $catalog['stripe_price_id']],
            ],
            'proration_behavior' => 'create_prorations',
            'metadata' => [
                'team_id' => (string) $team->id,
                'plan_key' => $plan->value,
            ],
        ]);

        $this->apply->handle(
            $team,
            $plan,
            $this->statusFromStripe(is_string($updated['status'] ?? null) ? $updated['status'] : 'active'),
            [
                'subscription_id' => $team->stripe_subscription_id,
                'price_id' => $catalog['stripe_price_id'],
            ],
            $team->coupon_code,
        );
    }

    public function cancel(Team $team): void
    {
        if (is_string($team->stripe_subscription_id) && $team->stripe_subscription_id !== '') {
            $updated = $this->request('post', '/v1/subscriptions/'.$team->stripe_subscription_id, [
                'cancel_at_period_end' => 'true',
            ]);
            $ends = isset($updated['current_period_end']) ? now()->setTimestamp((int) $updated['current_period_end']) : now()->addMonth();
        } else {
            $ends = now()->addMonth();
        }

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
        if (is_string($team->stripe_subscription_id) && $team->stripe_subscription_id !== '') {
            $this->request('post', '/v1/subscriptions/'.$team->stripe_subscription_id, [
                'cancel_at_period_end' => 'false',
            ]);
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
        $customerId = $this->customerId($team);
        $session = $this->request('post', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
        $url = $session['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe billing portal did not return a URL.');
        }

        $team->forceFill(['stripe_customer_id' => $customerId])->save();

        return $url;
    }

    public function handleWebhook(array $payload, ?string $signatureHeader, ?string $rawPayload = null): void
    {
        $this->verifySignature($payload, $signatureHeader, $rawPayload);

        $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];

        match ($type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($object),
            'customer.subscription.updated', 'customer.subscription.deleted' => $this->onSubscription($object),
            'invoice.paid' => $this->onInvoice($object, 'paid'),
            'invoice.payment_failed' => $this->onInvoiceFailed($object),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function applySession(Team $team, array $session): void
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $plan = PlanKey::tryFrom(is_string($metadata['plan_key'] ?? null) ? $metadata['plan_key'] : '')
            ?? $team->plan_key
            ?? PlanKey::Starter;
        $subscriptionId = is_string($session['subscription'] ?? null)
            ? $session['subscription']
            : (is_array($session['subscription'] ?? null) ? (string) ($session['subscription']['id'] ?? '') : null);

        $this->apply->handle(
            $team,
            $plan,
            SubscriptionStatus::Active,
            [
                'customer_id' => $session['customer'] ?? $team->stripe_customer_id,
                'subscription_id' => $subscriptionId,
            ],
            $team->coupon_code,
            null,
            null,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function onCheckoutCompleted(array $object): void
    {
        $team = $this->teamFromStripe($object);

        if ($team === null) {
            return;
        }

        $this->applySession($team, $object);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function onSubscription(array $object): void
    {
        $team = $this->teamFromStripe($object);

        if ($team === null) {
            return;
        }

        $status = $this->statusFromStripe(is_string($object['status'] ?? null) ? $object['status'] : 'active');
        $plan = PlanKey::tryFrom(is_string(($object['metadata']['plan_key'] ?? null)) ? $object['metadata']['plan_key'] : '')
            ?? $team->plan_key
            ?? PlanKey::Starter;
        $ends = isset($object['cancel_at']) && $object['cancel_at']
            ? now()->setTimestamp((int) $object['cancel_at'])
            : (isset($object['current_period_end']) && $status === SubscriptionStatus::Canceled
                ? now()->setTimestamp((int) $object['current_period_end'])
                : null);

        $this->apply->handle(
            $team,
            $plan,
            $status,
            [
                'customer_id' => $object['customer'] ?? $team->stripe_customer_id,
                'subscription_id' => $object['id'] ?? $team->stripe_subscription_id,
            ],
            $team->coupon_code,
            null,
            $ends,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function onInvoice(array $object, string $status): void
    {
        $team = $this->teamFromStripe($object);

        if ($team === null) {
            return;
        }

        $this->invoices->handle($team, [
            'stripe_id' => $object['id'] ?? null,
            'amount_cents' => (int) ($object['amount_paid'] ?? $object['amount_due'] ?? 0),
            'currency' => $object['currency'] ?? 'usd',
            'status' => $status,
            'hosted_invoice_url' => $object['hosted_invoice_url'] ?? null,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function onInvoiceFailed(array $object): void
    {
        $team = $this->teamFromStripe($object);

        if ($team === null) {
            return;
        }

        $this->apply->handle(
            $team,
            $team->plan_key ?? PlanKey::Starter,
            SubscriptionStatus::PastDue,
            [
                'customer_id' => $object['customer'] ?? $team->stripe_customer_id,
            ],
            $team->coupon_code,
            $team->trial_ends_at,
            $team->subscription_ends_at,
        );

        $this->onInvoice($object, 'open');

        $this->alerts->queue(
            $team,
            SignageAlert::SubscriptionIssue,
            __('Payment failed'),
            __('A subscription payment failed. Update the payment method to keep this organization in good standing.'),
            ['invoice' => $object['id'] ?? null],
            'payment-failed-'.$team->id,
            3600,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function teamFromStripe(array $object): ?Team
    {
        $customer = is_string($object['customer'] ?? null) ? $object['customer'] : null;
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $clientReference = $object['client_reference_id'] ?? $metadata['team_id'] ?? null;

        if (is_numeric($clientReference)) {
            $team = Team::query()->find((int) $clientReference);

            if ($team) {
                return $team;
            }
        }

        if ($customer) {
            return Team::query()->where('stripe_customer_id', $customer)->first();
        }

        return null;
    }

    protected function statusFromStripe(string $status): SubscriptionStatus
    {
        return SubscriptionStatus::tryFrom($status) ?? SubscriptionStatus::Active;
    }

    protected function customerId(Team $team): string
    {
        if (is_string($team->stripe_customer_id) && $team->stripe_customer_id !== '') {
            return $team->stripe_customer_id;
        }

        $owner = $team->owner();
        $customer = $this->request('post', '/v1/customers', [
            'name' => $team->name,
            'email' => $owner instanceof User ? $owner->email : null,
            'metadata' => ['team_id' => (string) $team->id],
        ]);
        $id = $customer['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Stripe did not return a customer id.');
        }

        $team->forceFill(['stripe_customer_id' => $id])->save();

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        $secret = config('billing.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'plan' => __('Stripe is not configured.'),
            ]);
        }

        $http = $this->http($secret);
        $response = $method === 'get'
            ? $http->get($path, $payload)
            : $http->asForm()->post($path, $this->flatten($payload));

        if ($response->failed()) {
            throw new RuntimeException('Stripe API request failed: '.$response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    protected function http(string $secret): PendingRequest
    {
        $base = config('billing.stripe.api_base');

        return Http::withToken($secret)
            ->baseUrl(is_string($base) && $base !== '' ? $base : 'https://api.stripe.com')
            ->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $flat = [...$flat, ...$this->flatten($value, $name)];
            } elseif ($value !== null) {
                $flat[$name] = $value;
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function verifySignature(array $payload, ?string $header, ?string $rawPayload = null): void
    {
        $secret = config('billing.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return;
        }

        if ($header === null || $header === '') {
            throw new HttpException(400, 'Missing Stripe signature.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($name === 't') {
                $timestamp = $value;
            }

            if ($name === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        $body = is_string($rawPayload) && $rawPayload !== '' ? $rawPayload : json_encode($payload);

        if ($timestamp === null || ! is_string($body)) {
            throw new HttpException(400, 'Invalid Stripe signature.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $valid = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
            }
        }

        if (! $valid) {
            throw new HttpException(400, 'Invalid Stripe signature.');
        }
    }
}
