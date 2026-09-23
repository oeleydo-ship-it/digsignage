<?php

namespace App\Support;

final class ClockTime
{
    /**
     * Parse H:i or H:i:s into minutes from midnight.
     */
    public static function toMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

        return max(0, min(24 * 60, ($hours * 60) + $minutes));
    }

    /**
     * Normalize a time string to H:i:s.
     */
    public static function normalize(string $time): string
    {
        $minutes = self::toMinutes($time);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d:00', $hours, $mins);
    }

    /**
     * Whether $minutes is inside [start, end). Overnight windows wrap midnight.
     * Identical start and end means the whole day.
     */
    public static function contains(int $minutes, int $start, int $end): bool
    {
        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $minutes >= $start && $minutes < $end;
        }

        return $minutes >= $start || $minutes < $end;
    }

    /**
     * Whether two half-open windows overlap, including overnight wrap.
     */
    public static function windowsOverlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        if ($aStart === $aEnd || $bStart === $bEnd) {
            return true;
        }

        return self::segmentsOverlap(self::segments($aStart, $aEnd), self::segments($bStart, $bEnd));
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    protected static function segments(int $start, int $end): array
    {
        if ($start < $end) {
            return [[$start, $end]];
        }

        return [[$start, 24 * 60], [0, $end]];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $left
     * @param  list<array{0: int, 1: int}>  $right
     */
    protected static function segmentsOverlap(array $left, array $right): bool
    {
        foreach ($left as $a) {
            foreach ($right as $b) {
                if ($a[0] < $b[1] && $b[0] < $a[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
