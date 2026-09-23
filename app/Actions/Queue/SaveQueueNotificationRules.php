<?php

namespace App\Actions\Queue;

use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Models\QueueNotificationRule;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SaveQueueNotificationRules
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes): void
    {
        $enabled = is_array($attributes['rules'] ?? null) ? $attributes['rules'] : [];
        $minutes = (int) $attributes['appointment_minutes_before'];

        DB::transaction(function () use ($team, $enabled, $minutes): void {
            foreach (QueueNotificationEvent::cases() as $event) {
                foreach (QueueNotificationChannel::cases() as $channel) {
                    QueueNotificationRule::query()->updateOrCreate(
                        ['team_id' => $team->id, 'event' => $event->value, 'channel' => $channel->value],
                        [
                            'is_enabled' => (bool) ($enabled[$event->value][$channel->value] ?? false),
                            'minutes_before' => $event === QueueNotificationEvent::AppointmentApproaching ? $minutes : null,
                        ],
                    );
                }
            }
        });
    }
}
