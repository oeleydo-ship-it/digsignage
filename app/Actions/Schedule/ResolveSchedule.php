<?php

namespace App\Actions\Schedule;

use App\Data\ResolvedPlayback;
use App\Enums\ScheduleContentType;
use App\Models\Schedule;
use App\Models\Screen;
use App\Support\ScheduleAudience;
use App\Support\ScheduleClock;
use DateTimeInterface;

class ResolveSchedule
{
    /**
     * Pick the highest-priority matching schedule for a screen, or the assigned channel.
     */
    public function handle(Screen $screen, DateTimeInterface $at): ResolvedPlayback
    {
        $screen->loadMissing(['location.parent', 'currentChannel', 'groups']);

        $candidates = Schedule::query()
            ->forTeam($screen->team)
            ->where('is_enabled', true)
            ->with(['targets.screenGroup.screens', 'targets.location', 'channel', 'playlist'])
            ->get()
            ->filter(fn (Schedule $schedule) => ScheduleAudience::includesScreen($schedule, $screen)
                && ScheduleClock::matches($schedule, $screen, $at))
            ->sort(function (Schedule $left, Schedule $right): int {
                $priority = $right->priority <=> $left->priority;

                if ($priority !== 0) {
                    return $priority;
                }

                $specificity = $right->specificity() <=> $left->specificity();

                if ($specificity !== 0) {
                    return $specificity;
                }

                return $right->updated_at->timestamp <=> $left->updated_at->timestamp;
            })
            ->values();

        $winner = $candidates->first();
        $timezone = ScheduleClock::timezoneFor($screen, $winner);

        if ($winner instanceof Schedule) {
            $channelId = $winner->content_type === ScheduleContentType::Channel
                ? $winner->channel_id
                : null;
            $playlistId = $winner->content_type === ScheduleContentType::Playlist
                ? $winner->playlist_id
                : ($winner->channel?->playlist_id);

            return new ResolvedPlayback(
                source: 'schedule',
                scheduleId: $winner->id,
                channelId: $channelId,
                playlistId: $playlistId,
                scheduleName: $winner->name,
                timezone: $timezone,
            );
        }

        return new ResolvedPlayback(
            source: 'fallback',
            scheduleId: null,
            channelId: $screen->current_channel_id,
            playlistId: $screen->currentChannel?->playlist_id,
            scheduleName: null,
            timezone: $timezone,
        );
    }
}
