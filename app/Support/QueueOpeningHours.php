<?php

namespace App\Support;

final class QueueOpeningHours
{
    /**
     * @return list<string>
     */
    public static function weekdays(): array
    {
        return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    /**
     * Default weekday windows. Times are 24-hour `H:i`.
     *
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    public static function defaults(): array
    {
        $hours = [];

        foreach (self::weekdays() as $day) {
            $closed = in_array($day, ['sat', 'sun'], true);

            $hours[$day] = [
                'open' => '09:00',
                'close' => '17:00',
                'closed' => $closed,
            ];
        }

        return $hours;
    }

    /**
     * Normalize a posted opening-hours payload to the canonical shape.
     *
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    public static function normalize(mixed $value): array
    {
        $source = is_array($value) ? $value : [];
        $hours = [];

        foreach (self::weekdays() as $day) {
            $row = is_array($source[$day] ?? null) ? $source[$day] : [];
            $defaults = self::defaults()[$day];

            $hours[$day] = [
                'open' => self::time($row['open'] ?? $defaults['open']),
                'close' => self::time($row['close'] ?? $defaults['close']),
                'closed' => filter_var($row['closed'] ?? $defaults['closed'], FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $hours;
    }

    protected static function time(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '09:00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return '09:00';
    }
}
