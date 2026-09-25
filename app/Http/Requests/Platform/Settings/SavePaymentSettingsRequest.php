<?php

namespace App\Http\Requests\Platform\Settings;

use App\Support\PlatformSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePaymentSettingsRequest extends FormRequest
{
    protected $redirectRoute = 'platform.settings.index';

    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /**
     * Blank secret fields keep the stored secret.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver' => ['required', 'in:fake,stripe'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'stripe_key' => ['nullable', 'string', 'max:255', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'stripe_secret' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255', 'regex:/^whsec_[A-Za-z0-9]+$/'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'string', 'max:255', 'regex:/^price_[A-Za-z0-9]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stripe_key.regex' => __('The publishable key starts with pk_test_ or pk_live_.'),
            'stripe_secret.regex' => __('The secret key starts with sk_test_, sk_live_, rk_test_ or rk_live_.'),
            'stripe_webhook_secret.regex' => __('The webhook signing secret starts with whsec_.'),
            'prices.*.regex' => __('Stripe price IDs start with price_.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('driver') !== 'stripe') {
                return;
            }

            $settings = app(PlatformSettings::class);
            $hasSecret = filled($this->input('stripe_secret'))
                || $settings->has('payments.stripe_secret')
                || filled(config('billing.stripe.secret'));

            if (! $hasSecret) {
                $validator->errors()->add('stripe_secret', __('Enter the Stripe secret key to take payments with Stripe.'));
            }

            $mode = fn (mixed $key): ?string => is_string($key) && $key !== ''
                ? (str_contains($key, '_live_') ? 'live' : 'test')
                : null;
            $public = $mode($this->input('stripe_key'));
            $secret = $mode($this->input('stripe_secret'));

            if ($public !== null && $secret !== null && $public !== $secret) {
                $validator->errors()->add('stripe_key', __('The publishable and secret keys must both be test keys or both be live keys.'));
            }
        }];
    }
}
