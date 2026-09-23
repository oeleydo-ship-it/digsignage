<?php

namespace App\Services\QueueNotifications;

use App\Enums\QueueNotificationChannel;

class WhatsAppQueueNotificationProvider extends WebhookQueueNotificationProvider
{
    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::WhatsApp;
    }

    protected function endpoint(): ?string
    {
        return config('queue-notifications.providers.whatsapp');
    }
}
