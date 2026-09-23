<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $queue_notification_rule_id
 * @property int|null $queue_ticket_id
 * @property int|null $queue_appointment_id
 * @property QueueNotificationEvent $event
 * @property QueueNotificationChannel $channel
 * @property string|null $destination
 * @property string $status
 * @property string $dedupe_key
 * @property array{subject?: string, body?: string, data?: array<string, mixed>} $payload
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
#[Fillable(['team_id', 'queue_notification_rule_id', 'queue_ticket_id', 'queue_appointment_id', 'event', 'channel', 'destination', 'status', 'dedupe_key', 'payload', 'attempts', 'error', 'sent_at'])]
class QueueNotificationDelivery extends Model
{
    use BelongsToTeam;

    protected function casts(): array
    {
        return [
            'event' => QueueNotificationEvent::class,
            'channel' => QueueNotificationChannel::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
