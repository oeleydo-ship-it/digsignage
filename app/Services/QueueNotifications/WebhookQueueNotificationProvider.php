<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Data\QueueNotificationMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class WebhookQueueNotificationProvider implements QueueNotificationProvider
{
    abstract protected function endpoint(): ?string;

    public function send(QueueNotificationMessage $message): void
    {
        $endpoint = $this->endpoint();

        if (blank($endpoint)) {
            throw new RuntimeException($this->channel()->label().' provider is not configured.');
        }

        Http::timeout(10)->retry(2, 250)->post($endpoint, [
            'channel' => $this->channel()->value,
            'destination' => $message->destination,
            'subject' => $message->subject,
            'body' => $message->body,
            'data' => $message->data,
        ])->throw();
    }
}
