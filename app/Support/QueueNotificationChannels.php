<?php

namespace App\Support;

use App\Enums\QueueNotificationChannel;
use App\Models\QueueSetting;
use App\Models\Team;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Where each customer notification channel is delivered for a team: the
 * SMS / WhatsApp provider and its credentials, whether browser push is on,
 * and email options. Stored on the team's queue settings; secrets are
 * encrypted with the application key.
 */
class QueueNotificationChannels
{
    /** @var array<string, list<string>> */
    public const DRIVERS = [
        'sms' => ['twilio', 'webhook'],
        'whatsapp' => ['twilio', 'meta', 'webhook'],
    ];

    /** @var list<string> */
    public const SECRETS = ['twilio_token', 'meta_token', 'webhook_secret'];

    /** @var array<string, list<string>> */
    public const FIELDS = [
        'sms' => ['driver', 'twilio_sid', 'twilio_token', 'twilio_from', 'webhook_url', 'webhook_secret'],
        'whatsapp' => ['driver', 'twilio_sid', 'twilio_token', 'twilio_from', 'meta_phone_number_id', 'meta_token', 'meta_template', 'meta_template_language', 'webhook_url', 'webhook_secret'],
        'push' => ['enabled'],
        'email' => ['enabled', 'reply_to'],
    ];

    /**
     * Decrypted settings for one channel. Falls back to the webhook URL in
     * .env (config/queue-notifications.php) when nothing is set up in the app.
     *
     * @return array<string, mixed>
     */
    public function config(int $teamId, QueueNotificationChannel $channel): array
    {
        $stored = $this->stored($teamId)[$channel->value] ?? [];
        $config = [];

        foreach (self::FIELDS[$channel->value] as $field) {
            $value = $stored[$field] ?? null;

            if (in_array($field, self::SECRETS, true) && is_string($value) && $value !== '') {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $value = null;
                }
            }

            $config[$field] = $value;
        }

        if (in_array($channel, [QueueNotificationChannel::Sms, QueueNotificationChannel::WhatsApp], true) && blank($config['driver'] ?? null)) {
            $fallback = config('queue-notifications.providers.'.$channel->value);

            if (is_string($fallback) && $fallback !== '') {
                $config['driver'] = 'webhook';
                $config['webhook_url'] = $fallback;
                $config['from_env'] = true;
            }
        }

        if ($channel === QueueNotificationChannel::Push) {
            $config['enabled'] = ($config['enabled'] ?? true) !== false;
        }

        if ($channel === QueueNotificationChannel::Email) {
            $config['enabled'] = ($config['enabled'] ?? true) !== false;
        }

        return $config;
    }

    /**
     * Whether the channel can deliver, and why not.
     *
     * @return array{ready: bool, label: string}
     */
    public function status(int $teamId, QueueNotificationChannel $channel): array
    {
        $config = $this->config($teamId, $channel);

        return match ($channel) {
            QueueNotificationChannel::Email => match (true) {
                ! $config['enabled'] => ['ready' => false, 'label' => __('Turned off')],
                in_array(config('mail.default'), ['log', 'array'], true) => ['ready' => false, 'label' => __('Platform email is not set up')],
                default => ['ready' => true, 'label' => __('Platform email')],
            },
            QueueNotificationChannel::Push => $config['enabled']
                ? ['ready' => true, 'label' => __('Browser push')]
                : ['ready' => false, 'label' => __('Turned off')],
            default => $this->providerStatus($config),
        };
    }

    /**
     * Whether a notification on this channel should be queued at all. Email
     * only needs to be on (the platform mailer decides where it goes, and a
     * development "log" mailer still counts as sending).
     */
    public function canSend(int $teamId, QueueNotificationChannel $channel): bool
    {
        return match ($channel) {
            QueueNotificationChannel::Email, QueueNotificationChannel::Push => (bool) $this->config($teamId, $channel)['enabled'],
            default => $this->status($teamId, $channel)['ready'],
        };
    }

    /**
     * Channel settings for the settings page: secrets are replaced by
     * "has_…" flags so they never reach the browser.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forPage(Team $team): array
    {
        $channels = [];

        foreach (QueueNotificationChannel::cases() as $channel) {
            $config = $this->config($team->id, $channel);

            foreach (self::SECRETS as $secret) {
                if (array_key_exists($secret, $config)) {
                    $config['has_'.$secret] = filled($config[$secret]);
                    unset($config[$secret]);
                }
            }

            $channels[$channel->value] = [
                ...$config,
                'status' => $this->status($team->id, $channel),
            ];
        }

        return $channels;
    }

    /**
     * Save one channel's settings. Blank secret fields keep what is stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function save(Team $team, QueueNotificationChannel $channel, array $attributes): void
    {
        $setting = QueueSetting::resolveForTeam($team);
        $all = $setting->settings ?? [];
        $channels = is_array($all['notification_channels'] ?? null) ? $all['notification_channels'] : [];
        $current = is_array($channels[$channel->value] ?? null) ? $channels[$channel->value] : [];

        foreach (self::FIELDS[$channel->value] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $value = $attributes[$field];

            if (in_array($field, self::SECRETS, true)) {
                if (blank($value)) {
                    continue;
                }

                $value = Crypt::encryptString((string) $value);
            }

            $current[$field] = is_string($value) ? trim($value) : $value;
        }

        // Switching providers clears the other provider's secrets.
        if (isset($current['driver']) && $current['driver'] !== 'twilio') {
            unset($current['twilio_token']);
        }

        if (isset($current['driver']) && $current['driver'] !== 'meta') {
            unset($current['meta_token']);
        }

        $channels[$channel->value] = $current;
        $all['notification_channels'] = $channels;
        $setting->forceFill(['settings' => $all])->save();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{ready: bool, label: string}
     */
    protected function providerStatus(array $config): array
    {
        return match ($config['driver'] ?? null) {
            'twilio' => filled($config['twilio_sid']) && filled($config['twilio_token']) && filled($config['twilio_from'])
                ? ['ready' => true, 'label' => 'Twilio']
                : ['ready' => false, 'label' => __('Twilio details missing')],
            'meta' => filled($config['meta_phone_number_id']) && filled($config['meta_token'])
                ? ['ready' => true, 'label' => 'Meta WhatsApp']
                : ['ready' => false, 'label' => __('Meta details missing')],
            'webhook' => filled($config['webhook_url'])
                ? ['ready' => true, 'label' => ($config['from_env'] ?? false) ? __('Webhook (.env)') : __('Webhook')]
                : ['ready' => false, 'label' => __('Webhook URL missing')],
            default => ['ready' => false, 'label' => __('Not set up')],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function stored(int $teamId): array
    {
        $settings = QueueSetting::query()->where('team_id', $teamId)->value('settings');
        $settings = is_string($settings) ? json_decode($settings, true) : $settings;
        $channels = is_array($settings) ? ($settings['notification_channels'] ?? null) : null;

        return is_array($channels) ? $channels : [];
    }
}
