<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $team_id
 * @property QueueNotificationEvent $event
 * @property QueueNotificationChannel $channel
 * @property bool $is_enabled
 * @property int|null $minutes_before
 */
#[Fillable(['team_id', 'event', 'channel', 'is_enabled', 'minutes_before'])]
class QueueNotificationRule extends Model
{
    use BelongsToTeam;

    protected function casts(): array
    {
        return [
            'event' => QueueNotificationEvent::class,
            'channel' => QueueNotificationChannel::class,
            'is_enabled' => 'boolean',
            'minutes_before' => 'integer',
        ];
    }
}
