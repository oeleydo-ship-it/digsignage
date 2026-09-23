<?php

namespace App\Support;

use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class ProofOfPlayFilters
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public Team $team,
        public array $filters,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public string $group,
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

        $group = (string) $request->string('group');

        if (! in_array($group, ['screen', 'location', 'content', 'playlist', 'channel', 'day'], true)) {
            $group = 'screen';
        }

        return new self(
            $team,
            [
                'screen_id' => $request->integer('screen_id') ?: null,
                'location_id' => $request->integer('location_id') ?: null,
                'playlist_id' => $request->integer('playlist_id') ?: null,
                'channel_id' => $request->integer('channel_id') ?: null,
                'content_id' => $request->string('content_id')->trim()->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'from' => $from->toDateString(),
                'until' => $until->toDateString(),
                'group' => $group,
            ],
            $from,
            $until,
            $group,
        );
    }
}
