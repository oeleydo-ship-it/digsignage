<?php

namespace App\Jobs;

use App\Data\QueueNotificationMessage;
use App\Models\QueueNotificationDelivery;
use App\Services\QueueNotifications\QueueNotificationProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SendQueueCustomerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $deliveryId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('queue-notification:'.$this->deliveryId))
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }

    public function handle(QueueNotificationProviderRegistry $providers): void
    {
        $delivery = QueueNotificationDelivery::query()->find($this->deliveryId);

        if ($delivery === null || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        $payload = $delivery->payload;
        $delivery->increment('attempts');

        try {
            $providers->for($delivery->channel)->send(new QueueNotificationMessage(
                destination: (string) $delivery->destination,
                subject: (string) ($payload['subject'] ?? 'Queue update'),
                body: (string) ($payload['body'] ?? ''),
                data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
                teamId: $delivery->team_id,
            ));
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            throw $exception;
        }
    }
}
