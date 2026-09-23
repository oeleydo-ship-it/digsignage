<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public WebhookDelivery $delivery) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('webhook-delivery:'.$this->delivery->id))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $delivery = $this->delivery->fresh(['endpoint']);

        if ($delivery === null) {
            return;
        }

        if ($delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint->is_active) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        $delivery->increment('attempts');

        try {
            $response = Http::timeout((int) config('partner.webhook_timeout'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'DigSignage-Webhooks/1.0',
                    'X-DigSignage-Event' => $delivery->event->value,
                    'X-DigSignage-Delivery' => $delivery->uuid,
                    'X-DigSignage-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            throw $exception;
        }

        if (! $response->successful()) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'response_code' => $response->status(),
                'last_error' => mb_substr($response->body(), 0, 1000),
                'delivered_at' => null,
            ])->save();

            throw new RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
        }

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Delivered,
            'response_code' => $response->status(),
            'last_error' => null,
            'delivered_at' => now(),
        ])->save();

        $endpoint->forceFill(['last_delivery_at' => now()])->save();
    }
}
