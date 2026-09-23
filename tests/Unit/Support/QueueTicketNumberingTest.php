<?php

namespace Tests\Unit\Support;

use App\Enums\QueueNumberingReset;
use App\Support\QueueTicketNumbering;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class QueueTicketNumberingTest extends TestCase
{
    public function test_daily_period_is_the_calendar_date(): void
    {
        $at = CarbonImmutable::parse('2026-09-21 23:45:00', 'UTC');

        $this->assertSame('2026-09-21', QueueTicketNumbering::periodKey(QueueNumberingReset::Daily, $at));
    }

    public function test_weekly_period_uses_iso_week_and_year(): void
    {
        $thursday = CarbonImmutable::parse('2026-09-24', 'UTC');
        $this->assertSame('2026-W39', QueueTicketNumbering::periodKey(QueueNumberingReset::Weekly, $thursday));

        $newYearThursday = CarbonImmutable::parse('2026-12-31', 'UTC');
        $this->assertSame('2026-W53', QueueTicketNumbering::periodKey(QueueNumberingReset::Weekly, $newYearThursday));

        $belongsToNextYear = CarbonImmutable::parse('2027-01-01', 'UTC');
        $this->assertSame('2026-W53', QueueTicketNumbering::periodKey(QueueNumberingReset::Weekly, $belongsToNextYear));
    }

    public function test_monthly_period_is_year_and_month(): void
    {
        $at = CarbonImmutable::parse('2026-09-21', 'UTC');

        $this->assertSame('2026-09', QueueTicketNumbering::periodKey(QueueNumberingReset::Monthly, $at));
    }

    public function test_never_uses_a_sentinel_period_key(): void
    {
        $this->assertSame(QueueTicketNumbering::NEVER_PERIOD, QueueTicketNumbering::periodKey(
            QueueNumberingReset::Never,
            CarbonImmutable::parse('2026-09-21', 'UTC'),
        ));
    }

    public function test_format_pads_the_sequence(): void
    {
        $this->assertSame('A001', QueueTicketNumbering::format('A', 1));
        $this->assertSame('B042', QueueTicketNumbering::format('B', 42));
    }
}
