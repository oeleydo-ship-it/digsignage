<?php

namespace App\Services\QueueNotifications;

use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppQueueNotificationProvider extends WebhookQueueNotificationProvider
{
    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::WhatsApp;
    }

    protected function twilioAddress(string $number): string
    {
        return str_starts_with($number, 'whatsapp:') ? $number : 'whatsapp:'.$number;
    }

    /**
     * Meta WhatsApp Cloud API. Messages to customers who have not written to
     * the business in the last 24 hours must use an approved template; when
     * one is set, the message text fills its first body variable.
     *
     * @param  array<string, mixed>  $config
     */
    protected function meta(array $config, QueueNotificationMessage $message): void
    {
        $phoneNumberId = (string) ($config['meta_phone_number_id'] ?? '');

        if ($phoneNumberId === '' || blank($config['meta_token'])) {
            throw new RuntimeException(__('The Meta phone number ID and access token are required.'));
        }

        $to = ltrim($this->phone($message->destination), '+');
        $template = trim((string) ($config['meta_template'] ?? ''));
        $payload = $template === ''
            ? ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $message->body]]
            : [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => trim((string) ($config['meta_template_language'] ?? '')) ?: 'en_US'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [['type' => 'text', 'text' => $message->body]],
                    ]],
                ],
            ];

        $response = Http::withToken((string) $config['meta_token'])
            ->timeout(15)
            ->post('https://graph.facebook.com/v20.0/'.rawurlencode($phoneNumberId).'/messages', $payload);

        $this->assertDelivered($response, 'Meta', 'message');
    }
}
