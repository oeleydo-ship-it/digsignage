<?php

namespace App\Support;

use App\Enums\QueueNumberingReset;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Ticket number uniqueness is scoped to a service and a numbering period.
 *
 * Tickets are unique on (team_id, queue_service_id, number, numbering_period).
 * Never-reset services use the sentinel NEVER_PERIOD instead of SQL NULL so
 * unique indexes work the same on MySQL and SQLite.
 */
final class QueueTicketNumbering
{
    public const SEQUENCE_PAD = 3;

    public const NEVER_PERIOD = 'never';

    /**
     * Compute the period key used to reset and unique ticket numbers.
     *
     * Daily: calendar date (Y-m-d). Weekly: ISO week (YYYY-Www). Monthly: Y-m.
     * Never: sentinel NEVER_PERIOD.
     */
    public static function periodKey(QueueNumberingReset $reset, DateTimeInterface $at): string
    {
        $moment = CarbonImmutable::instance($at);

        return match ($reset) {
            QueueNumberingReset::Daily => $moment->toDateString(),
            QueueNumberingReset::Weekly => sprintf('%d-W%02d', $moment->isoWeekYear(), $moment->isoWeek()),
            QueueNumberingReset::Monthly => $moment->format('Y-m'),
            QueueNumberingReset::Never => self::NEVER_PERIOD,
        };
    }

    /**
     * Format a display ticket such as A001.
     */
    public static function format(string $prefix, int $sequence, int $pad = self::SEQUENCE_PAD): string
    {
        return $prefix.str_pad((string) max(1, $sequence), $pad, '0', STR_PAD_LEFT);
    }
}
