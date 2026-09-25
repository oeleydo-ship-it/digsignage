<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Data\QueueNotificationMessage;
use App\Support\PhoneNumber;
use App\Support\QueueNotificationChannels;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Base for phone channels (SMS, WhatsApp): delivers through Twilio, the Meta
 * WhatsApp Cloud API or a signed webhook, as set up for the team.
 */
abstract class WebhookQueueNotificationProvider implements QueueNotificationProvider
{
    public function __construct(protected QueueNotificationChannels $channels) {}

    public function send(QueueNotificationMessage $message): void
    {
        $config = $this->channels->config((int) $message->teamId, $this->channel());

        match ($config['driver'] ?? null) {
            'twilio' => $this->twilio($config, $message),
            'meta' => $this->meta($config, $message),
            'webhook' => $this->webhook($config, $message),
            default => throw new RuntimeException(__(':channel is not set up. Choose a provider in Queue Configuration → Customer notifications.', ['channel' => $this->channel()->label()])),
        };
    }

    /**
     * Twilio's address format for this channel ("+1555…" or "whatsapp:+1555…").
     */
    protected function twilioAddress(string $number): string
    {
        return $number;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function twilio(array $config, QueueNotificationMessage $message): void
    {
        $to = $this->phone($message->destination);
        $from = PhoneNumber::e164((string) $config['twilio_from']) ?? (string) $config['twilio_from'];
        $sid = (string) $config['twilio_sid'];

        if ($sid === '' || blank($config['twilio_token']) || blank($config['twilio_from'])) {
            throw new RuntimeException(__('Twilio account SID, auth token and sender number are required.'));
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, (string) $config['twilio_token'])
            ->timeout(15)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json', [
                'To' => $this->twilioAddress($to),
                'From' => $this->twilioAddress($from),
                'Body' => $message->body,
            ]);

        $this->assertDelivered($response, 'Twilio', 'message');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function meta(array $config, QueueNotificationMessage $message): void
    {
        throw new RuntimeException(__('Meta is only available for WhatsApp.'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function webhook(array $config, QueueNotificationMessage $message): void
    {
        $url = (string) ($config['webhook_url'] ?? '');

        if ($url === '') {
            throw new RuntimeException(__('The webhook URL is not set.'));
        }

        $payload = [
            'channel' => $this->channel()->value,
            'destination' => PhoneNumber::e164($message->destination) ?? $message->destination,
            'subject' => $message->subject,
            'body' => $message->body,
            'data' => $message->data,
        ];
        $json = (string) json_encode($payload);
        $request = Http::timeout(10)->retry(2, 250, throw: false)->withBody($json, 'application/json');
        $secret = $config['webhook_secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            // Receivers verify with hash_hmac('sha256', $rawBody, $secret).
            $request = $request->withHeaders(['X-Signature' => 'sha256='.hash_hmac('sha256', $json, $secret)]);
        }

        $this->assertDelivered($request->post($url), __('The webhook'), 'response');
    }

    protected function phone(string $destination): string
    {
        return PhoneNumber::e164($destination)
            ?? throw new RuntimeException(__('":number" is not a phone number with a country code, such as +15551234567.', ['number' => $destination]));
    }

    protected function assertDelivered(Response $response, string $provider, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = $response->json('message') ?? $response->json('error.message') ?? mb_substr($response->body(), 0, 300);

        throw new RuntimeException(__(':provider rejected the :what (HTTP :status): :detail', [
            'provider' => $provider,
            'what' => $what,
            'status' => (string) $response->status(),
            'detail' => is_string($detail) ? $detail : json_encode($detail),
        ]));
    }
}
