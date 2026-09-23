<?php

namespace App\Support;

use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class AnalyticsFilters
{
    /**
     * @param  array<string, mixed>  $filters
     */
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
            : $until->subDays(6)->startOfDay();

        if ($from->greaterThan($until)) {
            [$from, $until] = [$until->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($until) > 366) {
            $from = $until->subDays(366)->startOfDay();
        }

        return new self($team, [
            'screen_id' => $request->integer('screen_id') ?: null,
            'location_id' => $request->integer('location_id') ?: null,
            'from' => $from->toDateString(),
            'until' => $until->toDateString(),
        ], $from, $until);
    }

    public function dayCount(): int
    {
        return max(1, (int) $this->from->startOfDay()->diffInDays($this->until->startOfDay()) + 1);
    }
}
