<?php

namespace App\Actions\Schedule;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\ScheduleContentType;
use App\Enums\ScheduleRecurrence;
use App\Enums\ScheduleTargetType;
use App\Models\Channel;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\ScheduleTarget;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Models\User;
use App\Support\ClockTime;
use App\Support\ScheduleAudience;
use App\Support\ScheduleClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSchedule
{
    /**
     * Create or update a schedule and its targets.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, Team $team, array $attributes, ?Schedule $schedule = null): Schedule
    {
        return DB::transaction(function () use ($user, $team, $attributes, $schedule) {
            $schedule ??= new Schedule([
                'team_id' => $team->id,
                'created_by' => $user->id,
            ]);

            $contentType = isset($attributes['content_type'])
                ? ScheduleContentType::from((string) $attributes['content_type'])
                : ($schedule->content_type ?? ScheduleContentType::Channel);

            $recurrence = isset($attributes['recurrence'])
                ? ScheduleRecurrence::from((string) $attributes['recurrence'])
                : ($schedule->recurrence ?? ScheduleRecurrence::Daily);

            $channelId = $this->nullableId($attributes['channel_id'] ?? $schedule->channel_id);
            $playlistId = $this->nullableId($attributes['playlist_id'] ?? $schedule->playlist_id);
            $timezone = is_string($attributes['timezone'] ?? null) && $attributes['timezone'] !== ''
                ? $attributes['timezone']
                : ($schedule->timezone ?? 'UTC');

            if (! ScheduleClock::isValidTimezone($timezone)) {
                throw ValidationException::withMessages([
                    'timezone' => __('Choose a valid timezone.'),
                ]);
            }

            if ($contentType === ScheduleContentType::Channel) {
                $playlistId = null;
                $this->assertChannel($channelId, $team);
            } else {
                $channelId = null;
                $this->assertPlaylist($playlistId, $team);
            }

            $weekdays = $this->weekdays($attributes['weekdays'] ?? $schedule->weekdays, $recurrence);
            $startsOn = (string) ($attributes['starts_on'] ?? '');
            $endsOn = array_key_exists('ends_on', $attributes)
                ? (filled($attributes['ends_on']) ? (string) $attributes['ends_on'] : null)
                : $schedule->ends_on?->toDateString();

            if ($startsOn === '') {
                throw ValidationException::withMessages([
                    'starts_on' => __('A start date is required.'),
                ]);
            }

            if ($endsOn !== null && $endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => __('The end date must be on or after the start date.'),
                ]);
            }

            if ($recurrence === ScheduleRecurrence::Weekly && $weekdays === []) {
                throw ValidationException::withMessages([
                    'weekdays' => __('Select at least one weekday for a weekly schedule.'),
                ]);
            }

            $targets = $this->normalizeTargets($team, $attributes['targets'] ?? null, $schedule);

            if ($targets === []) {
                throw ValidationException::withMessages([
                    'targets' => __('Choose at least one screen, group, or location.'),
                ]);
            }

            $schedule->fill([
                'name' => $attributes['name'] ?? $schedule->name,
                'description' => $attributes['description'] ?? $schedule->description,
                'content_type' => $contentType,
                'channel_id' => $channelId,
                'playlist_id' => $playlistId,
                'timezone' => $timezone,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'start_time' => ClockTime::normalize((string) ($attributes['start_time'] ?? $schedule->start_time ?? '00:00')),
                'end_time' => ClockTime::normalize((string) ($attributes['end_time'] ?? $schedule->end_time ?? '23:59')),
                'recurrence' => $recurrence,
                'weekdays' => $recurrence === ScheduleRecurrence::Weekly ? $weekdays : null,
                'priority' => max(1, min(1000, (int) ($attributes['priority'] ?? $schedule->priority ?? 100))),
                'is_enabled' => array_key_exists('is_enabled', $attributes)
                    ? (bool) $attributes['is_enabled']
                    : ($schedule->is_enabled ?? true),
                'updated_by' => $user->id,
            ]);

            $this->assertNoAmbiguousOverlap($team, $schedule, $targets);

            $schedule->save();

            $schedule->targets()->delete();

            foreach ($targets as $target) {
                ScheduleTarget::query()->create([
                    ...$target,
                    'team_id' => $team->id,
                    'schedule_id' => $schedule->id,
                ]);
            }

            return tap(
                $schedule->refresh()->load(['targets.screen', 'targets.screenGroup', 'targets.location', 'channel', 'playlist']),
                function (Schedule $saved) use ($user, $team): void {
                    app(RecordOrganizationAudit::class)->handle(
                        $team,
                        AuditAction::ScheduleChanged,
                        $user,
                        'schedule',
                        $saved->id,
                        null,
                        ['name' => $saved->name, 'enabled' => $saved->is_enabled],
                    );
                },
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $targets
     */
    protected function assertNoAmbiguousOverlap(Team $team, Schedule $schedule, array $targets): void
    {
        if (! $schedule->is_enabled) {
            return;
        }

        $screenIds = ScheduleAudience::screenIdsForTargets($team, $targets);

        if ($screenIds === []) {
            return;
        }

        $others = Schedule::query()
            ->forTeam($team)
            ->where('is_enabled', true)
            ->when($schedule->exists, fn ($query) => $query->whereKeyNot($schedule->id))
            ->where('priority', $schedule->priority)
            ->with(['targets.screenGroup.screens', 'targets.location'])
            ->get();

        foreach ($others as $other) {
            $otherTargets = [];

            foreach ($other->targets as $target) {
                $otherTargets[] = [
                    'target_type' => $target->target_type->value,
                    'screen_id' => $target->screen_id,
                    'screen_group_id' => $target->screen_group_id,
                    'location_id' => $target->location_id,
                ];
            }

            $otherIds = ScheduleAudience::screenIdsForTargets($team, $otherTargets);

            if (array_intersect($screenIds, $otherIds) === []) {
                continue;
            }

            if (ScheduleClock::windowsConflict($schedule, $other)) {
                throw ValidationException::withMessages([
                    'priority' => __('This window overlaps “:name” at the same priority. Raise or lower priority, or change the times.', [
                        'name' => $other->name,
                    ]),
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function normalizeTargets(Team $team, mixed $raw, Schedule $schedule): array
    {
        if (! is_array($raw)) {
            $existing = [];

            foreach ($schedule->targets as $target) {
                $existing[] = [
                    'target_type' => $target->target_type->value,
                    'screen_id' => $target->screen_id,
                    'screen_group_id' => $target->screen_group_id,
                    'location_id' => $target->location_id,
                ];
            }

            return $existing;
        }

        $normalized = [];

        foreach ($raw as $index => $target) {
            if (! is_array($target)) {
                continue;
            }

            $type = ScheduleTargetType::tryFrom((string) ($target['target_type'] ?? ''));

            if ($type === null) {
                throw ValidationException::withMessages([
                    "targets.{$index}.target_type" => __('Choose a valid target type.'),
                ]);
            }

            $row = [
                'target_type' => $type->value,
                'screen_id' => null,
                'screen_group_id' => null,
                'location_id' => null,
            ];

            if ($type === ScheduleTargetType::Screen) {
                $id = $this->nullableId($target['screen_id'] ?? null);

                if ($id === null || Screen::query()->forTeam($team)->whereKey($id)->doesntExist()) {
                    throw ValidationException::withMessages([
                        "targets.{$index}.screen_id" => __('The selected screen is not available to this team.'),
                    ]);
                }

                $row['screen_id'] = $id;
            } elseif ($type === ScheduleTargetType::ScreenGroup) {
                $id = $this->nullableId($target['screen_group_id'] ?? null);

                if ($id === null || ScreenGroup::query()->forTeam($team)->whereKey($id)->doesntExist()) {
                    throw ValidationException::withMessages([
                        "targets.{$index}.screen_group_id" => __('The selected screen group is not available to this team.'),
                    ]);
                }

                $row['screen_group_id'] = $id;
            } else {
                $id = $this->nullableId($target['location_id'] ?? null);

                if ($id === null || Location::query()->forTeam($team)->whereKey($id)->doesntExist()) {
                    throw ValidationException::withMessages([
                        "targets.{$index}.location_id" => __('The selected location is not available to this team.'),
                    ]);
                }

                $row['location_id'] = $id;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @return list<int>
     */
    protected function weekdays(mixed $raw, ScheduleRecurrence $recurrence): array
    {
        if ($recurrence !== ScheduleRecurrence::Weekly) {
            return [];
        }

        $days = [];

        if (is_array($raw)) {
            foreach ($raw as $day) {
                $value = (int) $day;

                if ($value >= 1 && $value <= 7) {
                    $days[] = $value;
                }
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    protected function assertChannel(?int $channelId, Team $team): void
    {
        if ($channelId === null || Channel::query()->forTeam($team)->whereKey($channelId)->doesntExist()) {
            throw ValidationException::withMessages([
                'channel_id' => __('Choose a channel that belongs to this team.'),
            ]);
        }
    }

    protected function assertPlaylist(?int $playlistId, Team $team): void
    {
        if ($playlistId === null || Playlist::query()->forTeam($team)->whereKey($playlistId)->doesntExist()) {
            throw ValidationException::withMessages([
                'playlist_id' => __('Choose a playlist that belongs to this team.'),
            ]);
        }
    }

    protected function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
