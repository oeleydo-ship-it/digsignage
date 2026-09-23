<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;
use Illuminate\Support\Facades\Mail;

class EmailQueueNotificationProvider implements QueueNotificationProvider
{
    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::Email;
    }

    public function send(QueueNotificationMessage $message): void
    {
        Mail::raw($message->body, fn ($mail) => $mail->to($message->destination)->subject($message->subject));
    }
}
