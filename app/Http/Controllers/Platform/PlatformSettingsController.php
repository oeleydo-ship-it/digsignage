<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Settings\SaveBrandingRequest;
use App\Http\Requests\Platform\Settings\SaveGeneralSettingsRequest;
use App\Http\Requests\Platform\Settings\SaveMailSettingsRequest;
use App\Http\Requests\Platform\Settings\SavePaymentSettingsRequest;
use App\Models\Plan;
use App\Support\BillingCatalog;
use App\Support\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Super admin → General settings: platform name, branding, payment gateway
 * (wired to each plan's price), outgoing email, and a checklist of what is
 * still required before going live.
 */
class PlatformSettingsController extends Controller
{
    public function __construct(
        protected PlatformSettings $settings,
        protected RecordPlatformAudit $audit,
    ) {}

    public function index(): Response
    {
        $plans = $this->plans();

        return Inertia::render('platform/settings/index', [
            'general' => [
                'name' => (string) config('app.name'),
                'support_email' => $this->settings->get('general.support_email'),
                'allow_registration' => $this->settings->registrationOpen(),
                'terms_url' => $this->settings->get('general.terms_url'),
                'privacy_url' => $this->settings->get('general.privacy_url'),
            ],
            'brand' => [
                'logo_url' => $this->settings->logoUrl(),
                'favicon_url' => $this->settings->faviconUrl(),
                'logo_tone' => $this->settings->logoTone(),
                'show_name' => $this->settings->showName(),
            ],
            'payments' => [
                'driver' => (string) config('billing.driver'),
                'currency' => strtoupper((string) config('billing.currency')),
                'trial_days' => (int) config('billing.trial_days'),
                'stripe_key' => config('billing.stripe.key'),
                'has_secret' => filled(config('billing.stripe.secret')),
                'has_webhook_secret' => filled(config('billing.stripe.webhook_secret')),
                'secret_hint' => $this->hint(config('billing.stripe.secret')),
                'webhook_url' => route('stripe.webhook'),
                'plans' => $plans,
            ],
            'mail' => [
                'mailer' => in_array(config('mail.default'), ['smtp', 'log'], true) ? (string) config('mail.default') : 'smtp',
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port') !== null ? (int) config('mail.mailers.smtp.port') : null,
                'username' => config('mail.mailers.smtp.username'),
                'has_password' => filled(config('mail.mailers.smtp.password')),
                'encryption' => $this->settings->get('mail.encryption')
                    ?? (config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ],
            'checklist' => $this->checklist($plans),
        ]);
    }

    public function updateGeneral(SaveGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->settings->save([
            'general.name' => trim((string) $data['name']),
            'general.support_email' => $data['support_email'] ?? null,
            'general.allow_registration' => (bool) $data['allow_registration'],
            'general.terms_url' => $data['terms_url'] ?? null,
            'general.privacy_url' => $data['privacy_url'] ?? null,
        ], $request->user()?->id);

        return $this->saved($request, 'general', $data);
    }

    public function updateBranding(SaveBrandingRequest $request): RedirectResponse
    {
        $changes = [];

        foreach (['logo' => 'branding.logo_path', 'favicon' => 'branding.favicon_path'] as $field => $key) {
            $upload = $request->file($field);
            $old = $this->settings->get($key);

            if ($upload instanceof UploadedFile) {
                $path = $upload->storePublicly('branding', 'public');
                $this->settings->save([$key => $path], $request->user()?->id);
                $changes[$field] = 'replaced';
            } elseif ($request->boolean('remove_'.$field)) {
                $this->settings->save([$key => null], $request->user()?->id);
                $changes[$field] = 'removed';
            } else {
                continue;
            }

            if (is_string($old) && $old !== '') {
                Storage::disk('public')->delete($old);
            }
        }

        $display = [];

        if ($request->has('logo_tone')) {
            $display['branding.logo_tone'] = $request->string('logo_tone')->toString();
        }

        if ($request->has('show_name')) {
            $display['branding.show_name'] = $request->boolean('show_name');
        }

        if ($display !== []) {
            $this->settings->save($display, $request->user()?->id);
            $changes['logo_tone'] = $this->settings->logoTone();
            $changes['show_name'] = $this->settings->showName();
        }

        return $this->saved($request, 'branding', $changes);
    }

    public function updatePayments(SavePaymentSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $values = [
            'payments.driver' => $data['driver'],
            'payments.currency' => strtolower((string) $data['currency']),
            'payments.trial_days' => (int) $data['trial_days'],
            'payments.stripe_key' => $data['stripe_key'] ?? null,
        ];

        // Secrets are write-only: blank keeps what is stored.
        foreach (['stripe_secret', 'stripe_webhook_secret'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                $values['payments.'.$secret] = $data[$secret];
            }
        }

        DB::transaction(function () use ($values, $data, $request): void {
            $this->settings->save($values, $request->user()?->id);

            foreach ((array) ($data['prices'] ?? []) as $key => $price) {
                $plan = Plan::query()->where('key', (string) $key)->first();

                if ($plan !== null && $plan->stripe_price_id !== ($price ?: null)) {
                    $plan->forceFill(['stripe_price_id' => $price ?: null])->save();
                    BillingCatalog::forgetPlanCache($plan->key);
                }
            }
        });

        return $this->saved($request, 'payments', [
            'driver' => $data['driver'],
            'currency' => $data['currency'],
            'trial_days' => $data['trial_days'],
            'prices' => $data['prices'] ?? [],
            'secrets_changed' => array_values(array_filter(['stripe_secret', 'stripe_webhook_secret'], fn ($key) => filled($data[$key] ?? null))),
        ]);
    }

    /**
     * Check the Stripe keys and that every plan's price exists and matches.
     */
    public function testPayments(Request $request): RedirectResponse
    {
        $secret = config('billing.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Save a Stripe secret key first.')]);

            return to_route('platform.settings.index');
        }

        $stripe = Http::withBasicAuth($secret, '')
            ->baseUrl(rtrim((string) config('billing.stripe.api_base'), '/'))
            ->acceptJson()
            ->timeout(15);

        try {
            $account = $stripe->get('/v1/account');
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe could not be reached: :message', ['message' => $exception->getMessage()])]);

            return to_route('platform.settings.index');
        }

        if (! $account->successful()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe rejected the secret key: :message', ['message' => (string) $account->json('error.message', 'HTTP '.$account->status())])]);

            return to_route('platform.settings.index');
        }

        $results = [];

        foreach ($this->plans() as $plan) {
            if ($plan['stripe_price_id'] === null) {
                $results[$plan['key']] = ['ok' => ! $plan['paid'], 'message' => $plan['paid'] ? __('No Stripe price set') : __('Free or custom plan')];

                continue;
            }

            $price = $stripe->get('/v1/prices/'.rawurlencode($plan['stripe_price_id']));

            if (! $price->successful()) {
                $results[$plan['key']] = ['ok' => false, 'message' => (string) $price->json('error.message', __('Price not found'))];

                continue;
            }

            $problems = [];

            if ($price->json('active') !== true) {
                $problems[] = __('price is archived');
            }

            if (strtolower((string) $price->json('currency')) !== strtolower((string) config('billing.currency'))) {
                $problems[] = __('currency is :currency', ['currency' => strtoupper((string) $price->json('currency'))]);
            }

            if ($plan['price_cents'] !== null && (int) $price->json('unit_amount') !== $plan['price_cents']) {
                $problems[] = __('amount is :amount', ['amount' => number_format(((int) $price->json('unit_amount')) / 100, 2)]);
            }

            if ($price->json('type') !== 'recurring') {
                $problems[] = __('price is not recurring');
            }

            $results[$plan['key']] = [
                'ok' => $problems === [],
                'message' => $problems === [] ? __('Matches the plan') : ucfirst(implode(', ', $problems)),
            ];
        }

        $failed = count(array_filter($results, fn (array $result) => ! $result['ok']));

        Inertia::flash('paymentCheck', [
            'account' => (string) ($account->json('settings.dashboard.display_name') ?? $account->json('id')),
            'livemode' => str_contains($secret, '_live_'),
            'plans' => $results,
        ]);
        Inertia::flash('toast', $failed === 0
            ? ['type' => 'success', 'message' => __('Stripe is connected and every plan is wired to a matching price.')]
            : ['type' => 'warning', 'message' => __('Stripe is connected, but :count plan(s) need attention.', ['count' => $failed])]);

        return to_route('platform.settings.index');
    }

    public function updateMail(SaveMailSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $values = [
            'mail.mailer' => $data['mailer'],
            'mail.host' => $data['host'] ?? null,
            'mail.port' => $data['port'] ?? null,
            'mail.username' => $data['username'] ?? null,
            'mail.encryption' => $data['encryption'],
            'mail.from_address' => $data['from_address'],
            'mail.from_name' => $data['from_name'],
        ];

        if (filled($data['password'] ?? null)) {
            $values['mail.password'] = $data['password'];
        }

        $this->settings->save($values, $request->user()?->id);

        return $this->saved($request, 'mail', [
            ...collect($data)->except('password')->all(),
            'password_changed' => filled($data['password'] ?? null),
        ]);
    }

    public function testMail(Request $request): RedirectResponse
    {
        $email = (string) $request->user()?->email;

        try {
            Mail::raw(
                __('This test message from :name confirms that outgoing email works.', ['name' => config('app.name')]),
                fn ($message) => $message->to($email)->subject(__(':name test email', ['name' => config('app.name')])),
            );
        } catch (Throwable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Sending failed: :message', ['message' => mb_substr($exception->getMessage(), 0, 300)])]);

            return to_route('platform.settings.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => config('mail.default') === 'log'
            ? __('Mail is set to "log", so the test was written to the application log instead of sent.')
            : __('Test email sent to :email.', ['email' => $email])]);

        return to_route('platform.settings.index');
    }

    /**
     * @param  array<string, mixed>  $after
     */
    protected function saved(Request $request, string $group, array $after): RedirectResponse
    {
        $this->audit->handle(PlatformAuditAction::PlatformSettingsUpdated, $request->user(), 'platform_settings', null, null, [
            'group' => $group,
            ...$after,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('platform.settings.index');
    }

    /**
     * @return list<array{key: string, name: string, price_cents: int|null, paid: bool, stripe_price_id: string|null}>
     */
    protected function plans(): array
    {
        return array_values(Plan::query()
            ->orderByRaw('price_cents is null')
            ->orderBy('price_cents')
            ->get(['key', 'name', 'price_cents', 'stripe_price_id'])
            ->map(fn (Plan $plan) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'price_cents' => $plan->price_cents,
                'paid' => (int) $plan->price_cents > 0,
                'stripe_price_id' => $plan->stripe_price_id ?: null,
            ])
            ->all());
    }

    /**
     * What a platform needs before it takes real customers.
     *
     * @param  list<array{key: string, name: string, price_cents: int|null, paid: bool, stripe_price_id: string|null}>  $plans
     * @return list<array{key: string, label: string, done: bool, required: bool, tab: string, hint: string}>
     */
    protected function checklist(array $plans): array
    {
        $unwired = array_values(array_map(
            fn (array $plan) => $plan['name'],
            array_filter($plans, fn (array $plan) => $plan['paid'] && $plan['stripe_price_id'] === null),
        ));
        $stripe = config('billing.driver') === 'stripe';
        $mailReady = ! in_array(config('mail.default'), ['log', 'array'], true) && filled(config('mail.from.address'));

        return [
            [
                'key' => 'name',
                'label' => __('Platform name'),
                'done' => $this->settings->has('general.name'),
                'required' => true,
                'tab' => 'general',
                'hint' => __('Shown in the browser tab, sidebar and every email.'),
            ],
            [
                'key' => 'support_email',
                'label' => __('Support email'),
                'done' => $this->settings->has('general.support_email'),
                'required' => true,
                'tab' => 'general',
                'hint' => __('Where customers can reach you.'),
            ],
            [
                'key' => 'logo',
                'label' => __('Custom logo'),
                'done' => $this->settings->has('branding.logo_path'),
                'required' => false,
                'tab' => 'branding',
                'hint' => __('Replaces the built-in mark.'),
            ],
            [
                'key' => 'mail',
                'label' => __('Outgoing email (SMTP)'),
                'done' => $mailReady,
                'required' => true,
                'tab' => 'mail',
                'hint' => $mailReady ? __('Invitations, bookings and alerts are delivered.') : __('Email is only written to the log, so nobody receives it.'),
            ],
            [
                'key' => 'payments',
                'label' => __('Payment gateway'),
                'done' => $stripe && filled(config('billing.stripe.secret')),
                'required' => true,
                'tab' => 'payments',
                'hint' => $stripe ? __('Stripe takes subscription payments.') : __('Test mode: plan changes are free and nobody is charged.'),
            ],
            [
                'key' => 'webhook',
                'label' => __('Stripe webhook'),
                'done' => $stripe && filled(config('billing.stripe.webhook_secret')),
                'required' => $stripe,
                'tab' => 'payments',
                'hint' => __('Keeps subscriptions in sync when customers pay, cancel or fail a payment.'),
            ],
            [
                'key' => 'plan_prices',
                'label' => __('Plans wired to prices'),
                'done' => $stripe && $unwired === [],
                'required' => $stripe,
                'tab' => 'payments',
                'hint' => $unwired === [] ? __('Every paid plan has a Stripe price.') : __('Missing: :plans', ['plans' => implode(', ', $unwired)]),
            ],
            [
                'key' => 'https',
                'label' => __('HTTPS site address'),
                'done' => str_starts_with((string) config('app.url'), 'https://'),
                'required' => true,
                'tab' => 'general',
                'hint' => __('Set APP_URL to your https:// address in .env.'),
            ],
        ];
    }

    private function hint(mixed $secret): ?string
    {
        return is_string($secret) && strlen($secret) > 8 ? '••••'.substr($secret, -4) : null;
    }
}
