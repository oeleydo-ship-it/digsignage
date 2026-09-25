<?php

namespace App\Services\QueueNotifications;

use App\Models\QueuePushSubscription;
use App\Support\PlatformSettings;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

/**
 * Browser push (Web Push with VAPID). The platform's key pair is created on
 * first use and kept in the platform settings, encrypted.
 */
class WebPushSender
{
    public function __construct(protected PlatformSettings $settings) {}

    /**
     * The public key browsers subscribe with.
     */
    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    /**
     * Send to every subscription; returns how many accepted it. Expired
     * subscriptions are removed.
     *
     * @param  Collection<int, QueuePushSubscription>  $subscriptions
     * @param  array{title: string, body: string, url?: string|null, tag?: string|null}  $notification
     */
    public function send(Collection $subscriptions, array $notification): int
    {
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $keys = $this->keys();
        $push = new WebPush([
            'VAPID' => [
                'subject' => $this->subject(),
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ],
        ], ['TTL' => 3600, 'urgency' => 'high']);

        $payload = (string) json_encode($notification);
        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($subscriptions as $subscription) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        $delivered = 0;
        $errors = [];

        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $byEndpoint->get($report->getEndpoint())?->delete();

                continue;
            }

            $errors[] = $report->getReason();
        }

        if ($delivered === 0 && $errors !== []) {
            throw new RuntimeException(__('Push delivery failed: :reason', ['reason' => mb_substr($errors[0], 0, 300)]));
        }

        return $delivered;
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    protected function keys(): array
    {
        $public = $this->settings->get('push.vapid_public');
        $private = $this->settings->get('push.vapid_private');

        if (is_string($public) && $public !== '' && is_string($private) && $private !== '') {
            return ['publicKey' => $public, 'privateKey' => $private];
        }

        $keys = self::createVapidKeys();
        $this->settings->save([
            'push.vapid_public' => $keys['publicKey'],
            'push.vapid_private' => $keys['privateKey'],
        ]);

        return ['publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']];
    }

    protected function subject(): string
    {
        $email = $this->settings->get('general.support_email');

        return is_string($email) && $email !== '' ? 'mailto:'.$email : (string) config('app.url');
    }

    /**
     * A P-256 key pair in the base64url form Web Push uses. The OpenSSL
     * config is passed explicitly so this also works on Windows PHP builds,
     * which cannot find openssl.cnf on their own.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public static function createVapidKeys(): array
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $config = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        $configured = getenv('OPENSSL_CONF');

        // Also covers an OPENSSL_CONF left pointing at a missing file.
        if (($configured === false || ! is_file($configured)) && is_file($config)) {
            $options['config'] = $config;
        }

        $key = openssl_pkey_new($options);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException(__('Could not create the push notification keys: :error', ['error' => (string) openssl_error_string()]));
        }

        $pad = fn (string $value): string => str_pad($value, 32, "\0", STR_PAD_LEFT);
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return [
            'publicKey' => $encode("\x04".$pad($details['ec']['x']).$pad($details['ec']['y'])),
            'privateKey' => $encode($pad($details['ec']['d'])),
        ];
    }
}
