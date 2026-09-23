<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Screen;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ScheduleCalendar
{
    /**
     * Expand schedules into calendar blocks for a week.
     *
     * @param  iterable<int, Schedule>  $schedules
     * @return list<array<string, mixed>>
     */
    public static function occurrences(
        iterable $schedules,
        DateTimeInterface $weekStart,
        ?Screen $screen = null,
        string $timezone = 'UTC',
    ): array {
        $monday = CarbonImmutable::parse($weekStart, $timezone)->startOfWeek(CarbonImmutable::MONDAY);
        $events = [];

        foreach ($schedules as $schedule) {
            if ($screen !== null && ! ScheduleAudience::includesScreen($schedule, $screen)) {
                continue;
            }

            for ($offset = 0; $offset < 7; $offset++) {
                $day = $monday->addDays($offset);
                $instant = $day->setTimeFromTimeString($schedule->start_time)->shiftTimezone($timezone);

                if ($screen !== null) {
                    if (! ScheduleClock::matches($schedule, $screen, $instant)) {
                        $wrap = $day->setTime(0, 0)->shiftTimezone($timezone);

                        if (! ScheduleClock::matches($schedule, $screen, $wrap)) {
                            continue;
                        }
                    }
                } elseif (! self::matchesInTimezone($schedule, $day, $timezone)) {
                    continue;
                }

                $startMinutes = ClockTime::toMinutes($schedule->start_time);
                $endMinutes = ClockTime::toMinutes($schedule->end_time);

                if ($startMinutes < $endMinutes || $startMinutes === $endMinutes) {
                    $events[] = self::block($schedule, $day->toDateString(), $startMinutes, $endMinutes === $startMinutes ? 24 * 60 : $endMinutes, $timezone);
                } else {
                    $events[] = self::block($schedule, $day->toDateString(), $startMinutes, 24 * 60, $timezone);

                    $next = $day->addDay();

                    if (self::dayAllowed($schedule, $next, $screen, $timezone)) {
                        $events[] = self::block($schedule, $next->toDateString(), 0, $endMinutes, $timezone);
                    }
                }
            }
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function block(Schedule $schedule, string $date, int $startMinutes, int $endMinutes, string $timezone): array
    {
        return [
            'schedule_id' => $schedule->id,
            'name' => $schedule->name,
            'date' => $date,
            'start_minutes' => $startMinutes,
            'end_minutes' => $endMinutes,
            'priority' => $schedule->priority,
            'content_type' => $schedule->content_type->value,
            'channel_name' => $schedule->channel?->name,
            'playlist_name' => $schedule->playlist?->name,
            'timezone' => $timezone,
            'is_enabled' => $schedule->is_enabled,
        ];
    }

    protected static function matchesInTimezone(Schedule $schedule, CarbonImmutable $day, string $timezone): bool
    {
        $probe = $day->setTimeFromTimeString($schedule->start_time);

        $screen = new Screen([
            'timezone' => $timezone,
            'team_id' => $schedule->team_id,
        ]);

        return ScheduleClock::matches($schedule, $screen, $probe);
    }

    protected static function dayAllowed(Schedule $schedule, CarbonImmutable $day, ?Screen $screen, string $timezone): bool
    {
        $probe = $day->setTime(0, 1);

        if ($screen !== null) {
            return ScheduleClock::matches($schedule, $screen, $probe);
        }

        return self::matchesInTimezone($schedule, $day, $timezone);
    }
}
