<?php

namespace App\Services\QueueNotifications;

use App\Enums\QueueNotificationChannel;

class SmsQueueNotificationProvider extends WebhookQueueNotificationProvider
{
    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::Sms;
    }
}
