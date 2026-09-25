<?php

namespace App\Support;

use App\Billing\BillingGateway;
use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Platform-wide settings managed on Super admin → General settings.
 *
 * Saved values override the matching .env configuration at runtime; clearing
 * a value falls back to .env again. Secrets are encrypted at rest and only
 * decrypted in memory.
 */
class PlatformSettings
{
    private const CACHE_ROWS = 'platform-settings:rows';

    private const CACHE_VERSION = 'platform-settings:version';

    /**
     * Every setting: its type, whether it is secret, and the config key it
     * overrides.
     *
     * @var array<string, array{type: 'string'|'bool'|'int', secret?: bool, config?: string}>
     */
    public const DEFINITIONS = [
        'general.name' => ['type' => 'string', 'config' => 'app.name'],
        'general.support_email' => ['type' => 'string'],
        'general.allow_registration' => ['type' => 'bool'],
        'general.terms_url' => ['type' => 'string'],
        'general.privacy_url' => ['type' => 'string'],

        'branding.logo_path' => ['type' => 'string'],
        'branding.favicon_path' => ['type' => 'string'],
        'branding.logo_tone' => ['type' => 'string'],
        'branding.show_name' => ['type' => 'bool'],

        'payments.driver' => ['type' => 'string', 'config' => 'billing.driver'],
        'payments.currency' => ['type' => 'string', 'config' => 'billing.currency'],
        'payments.trial_days' => ['type' => 'int', 'config' => 'billing.trial_days'],
        'payments.stripe_key' => ['type' => 'string', 'config' => 'billing.stripe.key'],
        'payments.stripe_secret' => ['type' => 'string', 'secret' => true, 'config' => 'billing.stripe.secret'],
        'payments.stripe_webhook_secret' => ['type' => 'string', 'secret' => true, 'config' => 'billing.stripe.webhook_secret'],

        'push.vapid_public' => ['type' => 'string'],
        'push.vapid_private' => ['type' => 'string', 'secret' => true],

        'mail.mailer' => ['type' => 'string', 'config' => 'mail.default'],
        'mail.host' => ['type' => 'string', 'config' => 'mail.mailers.smtp.host'],
        'mail.port' => ['type' => 'int', 'config' => 'mail.mailers.smtp.port'],
        'mail.username' => ['type' => 'string', 'config' => 'mail.mailers.smtp.username'],
        'mail.password' => ['type' => 'string', 'secret' => true, 'config' => 'mail.mailers.smtp.password'],
        'mail.encryption' => ['type' => 'string'],
        'mail.from_address' => ['type' => 'string', 'config' => 'mail.from.address'],
        'mail.from_name' => ['type' => 'string', 'config' => 'mail.from.name'],
    ];

    /** The settings version this process last applied. */
    private ?string $appliedVersion = null;

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * Stored values, decrypted and typed. Keys without a stored value are
     * absent.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = [];

        foreach ($this->rows() as $key => [$value, $encrypted]) {
            $definition = self::DEFINITIONS[$key] ?? null;

            if ($definition === null || $value === null) {
                continue;
            }

            if ($encrypted) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    // APP_KEY changed: the secret must be entered again.
                    continue;
                }
            }

            $values[$key] = match ($definition['type']) {
                'bool' => $value === '1',
                'int' => (int) $value,
                default => $value,
            };
        }

        return $this->values = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Save settings. Null or empty values are removed so .env applies again.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? throw new InvalidArgumentException("Unknown platform setting [{$key}].");

            if ($value === null || $value === '') {
                PlatformSetting::query()->whereKey($key)->delete();

                continue;
            }

            $stored = match ($definition['type']) {
                'bool' => $value ? '1' : '0',
                'int' => (string) (int) $value,
                default => (string) $value,
            };
            $secret = ($definition['secret'] ?? false) === true;

            PlatformSetting::query()->updateOrCreate(['key' => $key], [
                'value' => $secret ? Crypt::encryptString($stored) : $stored,
                'encrypted' => $secret,
                'updated_by' => $userId,
            ]);
        }

        $this->flush();
        $this->apply();
    }

    /**
     * Push stored values into the runtime configuration.
     */
    public function apply(): void
    {
        $this->values = null;
        $overrides = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            if (isset($definition['config']) && $this->has($key)) {
                $overrides[$definition['config']] = $this->get($key);
            }
        }

        if ($this->has('mail.encryption')) {
            $overrides['mail.mailers.smtp.scheme'] = $this->get('mail.encryption') === 'ssl' ? 'smtps' : 'smtp';
        }

        if ($overrides !== []) {
            config($overrides);
        }

        // Drop services built with the old configuration.
        app()->forgetInstance(BillingGateway::class);

        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }

        $this->appliedVersion = $this->version();
    }

    /**
     * Long-running processes (queue workers) call this before each job so
     * they pick up settings saved after they started.
     */
    public function refreshIfChanged(): void
    {
        if ($this->version() !== $this->appliedVersion) {
            $this->apply();
        }
    }

    public function flush(): void
    {
        $this->values = null;

        try {
            Cache::forget(self::CACHE_ROWS);
            Cache::forever(self::CACHE_VERSION, (string) Str::uuid());
        } catch (Throwable) {
            // Cache store unavailable; values are re-read from the database.
        }
    }

    public function platformName(): string
    {
        return (string) config('app.name');
    }

    public function logoUrl(): ?string
    {
        return $this->publicUrl($this->get('branding.logo_path'));
    }

    public function faviconUrl(): ?string
    {
        return $this->publicUrl($this->get('branding.favicon_path'));
    }

    /**
     * How the logo is tinted on the dark sidebar: original colours, white or black.
     */
    public function logoTone(): string
    {
        $tone = $this->get('branding.logo_tone');

        return in_array($tone, ['white', 'black'], true) ? $tone : 'original';
    }

    public function showName(): bool
    {
        return (bool) $this->get('branding.show_name', true);
    }

    public function registrationOpen(): bool
    {
        return (bool) $this->get('general.allow_registration', true);
    }

    private function publicUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private function version(): string
    {
        try {
            return (string) Cache::get(self::CACHE_VERSION, 'initial');
        } catch (Throwable) {
            return 'initial';
        }
    }

    /**
     * Raw rows (secrets still encrypted), cached so a request costs no query.
     *
     * @return array<string, array{0: string|null, 1: bool}>
     */
    private function rows(): array
    {
        $load = function (): array {
            try {
                return PlatformSetting::query()
                    ->get(['key', 'value', 'encrypted'])
                    ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => [$setting->value, $setting->encrypted]])
                    ->all();
            } catch (Throwable) {
                // Table not migrated yet.
                return [];
            }
        };

        try {
            return Cache::rememberForever(self::CACHE_ROWS, $load);
        } catch (Throwable) {
            return $load();
        }
    }
}
