<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Enums\QueueNotificationChannel;

class QueueNotificationProviderRegistry
{
    /** @var array<string, QueueNotificationProvider> */
    protected array $providers;

    public function __construct(
        EmailQueueNotificationProvider $email,
        SmsQueueNotificationProvider $sms,
        WhatsAppQueueNotificationProvider $whatsApp,
        PushQueueNotificationProvider $push,
    ) {
        $this->providers = collect([$email, $sms, $whatsApp, $push])
            ->mapWithKeys(fn (QueueNotificationProvider $provider) => [$provider->channel()->value => $provider])
            ->all();
    }

    public function for(QueueNotificationChannel $channel): QueueNotificationProvider
    {
        return $this->providers[$channel->value];
    }
}
