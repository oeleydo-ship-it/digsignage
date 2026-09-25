<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;
use App\Support\QueueNotificationChannels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sends through the platform mailer (Super admin → General settings → Email),
 * with the team's optional reply-to address.
 */
class EmailQueueNotificationProvider implements QueueNotificationProvider
{
    public function __construct(protected QueueNotificationChannels $channels) {}

    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::Email;
    }

    public function send(QueueNotificationMessage $message): void
    {
        $config = $this->channels->config((int) $message->teamId, $this->channel());

        if (! $config['enabled']) {
            throw new RuntimeException(__('Email notifications are turned off.'));
        }

        if (filter_var($message->destination, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('":email" is not a valid email address.', ['email' => $message->destination]));
        }

        $replyTo = $config['reply_to'] ?? null;

        Mail::raw($message->body, function ($mail) use ($message, $replyTo): void {
            $mail->to($message->destination)->subject($message->subject);

            if (is_string($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
                $mail->replyTo($replyTo);
            }
        });
    }
}
