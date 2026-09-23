<?php

namespace App\Support;

use App\Enums\ScheduleRecurrence;
use App\Models\Schedule;
use App\Models\Screen;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ScheduleClock
{
    /**
     * Timezone used to evaluate a schedule on a screen.
     */
    public static function timezoneFor(Screen $screen, ?Schedule $schedule = null): string
    {
        $screen->loadMissing('location.parent');

        if (filled($screen->timezone) && self::isValidTimezone($screen->timezone)) {
            return $screen->timezone;
        }

        $locationTimezone = $screen->location?->resolvedTimezone();

        if (is_string($locationTimezone) && self::isValidTimezone($locationTimezone)) {
            return $locationTimezone;
        }

        if ($schedule !== null && self::isValidTimezone($schedule->timezone)) {
            return $schedule->timezone;
        }

        return 'UTC';
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Whether the schedule is active for the screen at the given instant.
     */
    public static function matches(Schedule $schedule, Screen $screen, DateTimeInterface $at): bool
    {
        if (! $schedule->is_enabled) {
            return false;
        }

        $timezone = self::timezoneFor($screen, $schedule);
        $local = CarbonImmutable::parse($at)->setTimezone($timezone);
        $date = $local->toDateString();
        $startsOn = $schedule->starts_on->toDateString();
        $endsOn = $schedule->ends_on?->toDateString();

        if ($date < $startsOn) {
            return false;
        }

        if ($endsOn !== null && $date > $endsOn) {
            return false;
        }

        if ($schedule->recurrence === ScheduleRecurrence::Once && $date !== $startsOn) {
            return false;
        }

        if ($schedule->recurrence === ScheduleRecurrence::Weekly) {
            $weekdays = self::weekdayList($schedule);
            $iso = $local->isoWeekday();

            if (! in_array($iso, $weekdays, true)) {
                return false;
            }
        }

        $minutes = ($local->hour * 60) + $local->minute;

        return ClockTime::contains(
            $minutes,
            ClockTime::toMinutes($schedule->start_time),
            ClockTime::toMinutes($schedule->end_time),
        );
    }

    /**
     * Whether two enabled schedules could play on the same screen at the same time.
     */
    public static function windowsConflict(Schedule $a, Schedule $b): bool
    {
        $aStartDate = $a->starts_on->toDateString();
        $bStartDate = $b->starts_on->toDateString();
        $aEndDate = $a->ends_on?->toDateString() ?? '9999-12-31';
        $bEndDate = $b->ends_on?->toDateString() ?? '9999-12-31';

        if ($aEndDate < $bStartDate || $bEndDate < $aStartDate) {
            return false;
        }

        if (! self::recurrenceCanMeet($a, $b, max($aStartDate, $bStartDate), min($aEndDate, $bEndDate))) {
            return false;
        }

        return ClockTime::windowsOverlap(
            ClockTime::toMinutes($a->start_time),
            ClockTime::toMinutes($a->end_time),
            ClockTime::toMinutes($b->start_time),
            ClockTime::toMinutes($b->end_time),
        );
    }

    protected static function recurrenceCanMeet(Schedule $a, Schedule $b, string $from, string $to): bool
    {
        $daysA = self::activeIsoDays($a);
        $daysB = self::activeIsoDays($b);

        if ($daysA !== null && $daysB !== null && array_intersect($daysA, $daysB) === []) {
            return false;
        }

        if ($a->recurrence === ScheduleRecurrence::Once) {
            $date = $a->starts_on->toDateString();

            return $date >= $from && $date <= $to && self::dateMatches($b, $date);
        }

        if ($b->recurrence === ScheduleRecurrence::Once) {
            $date = $b->starts_on->toDateString();

            return $date >= $from && $date <= $to && self::dateMatches($a, $date);
        }

        return true;
    }

    /**
     * @return list<int>|null
     */
    protected static function activeIsoDays(Schedule $schedule): ?array
    {
        if ($schedule->recurrence === ScheduleRecurrence::Weekly) {
            $days = [];

            foreach ($schedule->weekdays ?? [] as $day) {
                $days[] = (int) $day;
            }

            return $days;
        }

        if ($schedule->recurrence === ScheduleRecurrence::Once) {
            return [$schedule->starts_on->isoWeekday()];
        }

        return null;
    }

    protected static function dateMatches(Schedule $schedule, string $date): bool
    {
        if ($date < $schedule->starts_on->toDateString()) {
            return false;
        }

        if ($schedule->ends_on !== null && $date > $schedule->ends_on->toDateString()) {
            return false;
        }

        if ($schedule->recurrence === ScheduleRecurrence::Once) {
            return $date === $schedule->starts_on->toDateString();
        }

        if ($schedule->recurrence === ScheduleRecurrence::Weekly) {
            $iso = CarbonImmutable::parse($date)->isoWeekday();

            return in_array($iso, self::weekdayList($schedule), true);
        }

        return true;
    }

    /**
     * @return list<int>
     */
    protected static function weekdayList(Schedule $schedule): array
    {
        $days = [];

        foreach ($schedule->weekdays ?? [] as $day) {
            $days[] = (int) $day;
        }

        return $days;
    }
}
