<?php

namespace App\Support;

use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class QueueAnalyticsFilters
{
    /** @param array<string, int|string|null> $filters */
    public function __construct(
        public Team $team,
        public array $filters,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
    ) {}

    public static function fromRequest(Request $request, Team $team): self
    {
        $until = filled($request->input('until'))
            ? CarbonImmutable::parse((string) $request->input('until'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $from = filled($request->input('from'))
            ? CarbonImmutable::parse((string) $request->input('from'))->startOfDay()
            : $until->subDays(29)->startOfDay();

        if ($from->greaterThan($until)) {
            [$from, $until] = [$until->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($until) > 366) {
            $from = $until->subDays(366)->startOfDay();
        }

        $slaMinutes = max(1, min(1440, $request->integer('sla_minutes', 20)));

        return new self(
            team: $team,
            filters: [
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
                'location_id' => $request->integer('location_id') ?: null,
                'service_id' => $request->integer('service_id') ?: null,
                'counter_id' => $request->integer('counter_id') ?: null,
                'employee_id' => $request->integer('employee_id') ?: null,
                'sla_minutes' => $slaMinutes,
            ],
            from: $from,
            until: $until,
        );
    }
}
