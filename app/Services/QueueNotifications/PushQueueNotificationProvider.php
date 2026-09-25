<?php

namespace App\Services\QueueNotifications;

use App\Contracts\QueueNotificationProvider;
use App\Data\QueueNotificationMessage;
use App\Enums\QueueNotificationChannel;
use App\Models\QueuePushSubscription;
use App\Models\QueueTicket;
use App\Support\QueueNotificationChannels;
use RuntimeException;

/**
 * Browser push to customers who tapped "Notify me" on their virtual ticket.
 * The delivery destination is the ticket's public token.
 */
class PushQueueNotificationProvider implements QueueNotificationProvider
{
    public function __construct(
        protected QueueNotificationChannels $channels,
        protected WebPushSender $sender,
    ) {}

    public function channel(): QueueNotificationChannel
    {
        return QueueNotificationChannel::Push;
    }

    public function send(QueueNotificationMessage $message): void
    {
        if (! $this->channels->config((int) $message->teamId, $this->channel())['enabled']) {
            throw new RuntimeException(__('Push notifications are turned off.'));
        }

        $ticket = QueueTicket::query()->where('public_token', $message->destination)->first();
        $subscriptions = $ticket === null
            ? collect()
            : QueuePushSubscription::query()->where('queue_ticket_id', $ticket->id)->get();

        if ($subscriptions->isEmpty()) {
            throw new RuntimeException(__('The customer has not turned on notifications.'));
        }

        $delivered = $this->sender->send($subscriptions, [
            'title' => $message->subject,
            'body' => $message->body,
            'url' => route('queue.virtual.ticket', $message->destination),
            'tag' => 'queue-ticket-'.$ticket?->id,
        ]);

        if ($delivered === 0) {
            throw new RuntimeException(__('The customer\'s browser no longer accepts notifications.'));
        }
    }
}
