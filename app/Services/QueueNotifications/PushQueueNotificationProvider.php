<?php

namespace App\Services\QueueNotifications;

use App\Enums\QueueNotificationChannel;

class PushQueueNotificationProvider extends WebhookQueueNotificationProvider
{
    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::Push;
    }

    protected function endpoint(): ?string
    {
        return config('queue-notifications.providers.push');
    }
}
