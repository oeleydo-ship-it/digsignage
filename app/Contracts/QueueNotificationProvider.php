<?php

namespace App\Contracts;

use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;

interface QueueNotificationProvider
{
    public function channel(): QueueNotificationChannel;

    public function send(QueueNotificationMessage $message): void;
}
