<?php

namespace App\Actions\Analytics;

use App\Enums\AnalyticsEventType;
use App\Models\PlayerAnalyticsEvent;
use App\Models\PlayerPlaybackEvent;
use App\Models\Screen;
use App\Support\AnalyticsFilters;
use App\Support\AnalyticsReportCache;
use App\Support\ClassifyPlayerAnalytics;
use App\Support\ScreenHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueryAnalytics
{
    /**
     * @return array{
     *     screens: array{
     *         online: int,
     *         warning: int,
     *         offline: int,
     *         disabled: int,
     *         uptime_percent: float,
     *         storage_warnings: int,
     *         versions: list<array{label: string, value: int}>,
     *         status: array<int, array{label: string, value: int}>
     *     },
     *     content: array{
     *         plays: int,
     *         duration_ms: int,
     *         screens_reached: int,
     *         locations_reached: int,
     *         top: list<array{label: string, value: int}>,
     *         daily: list<array{label: string, value: int}>
     *     },
     *     operations: array{
     *         failed_downloads: int,
     *         crashes: int,
     *         sync_failures: int,
     *         device_errors: int,
     *         command_failures: int,
     *         daily: list<array{label: string, downloads: int, crashes: int, sync: int, errors: int, commands: int}>
     *     }
     * }
     */
    public function handle(AnalyticsFilters $filters): array
    {
        // The report fans out into ~8 aggregate queries; cache per team and
        // filter set. New telemetry bumps the team's report version.
        return AnalyticsReportCache::remember(
            $filters->team->id,
            $filters->filters,
            fn () => $this->report($filters),
        );
    }

    /**
     * @return array{
     *     screens: array{
     *         online: int,
     *         warning: int,
     *         offline: int,
     *         disabled: int,
     *         uptime_percent: float,
     *         storage_warnings: int,
     *         versions: list<array{label: string, value: int}>,
     *         status: array<int, array{label: string, value: int}>
     *     },
     *     content: array{
     *         plays: int,
     *         duration_ms: int,
     *         screens_reached: int,
     *         locations_reached: int,
     *         top: list<array{label: string, value: int}>,
     *         daily: list<array{label: string, value: int}>
     *     },
     *     operations: array{
     *         failed_downloads: int,
     *         crashes: int,
     *         sync_failures: int,
     *         device_errors: int,
     *         command_failures: int,
     *         daily: list<array{label: string, downloads: int, crashes: int, sync: int, errors: int, commands: int}>
     *     }
     * }
     */
    protected function report(AnalyticsFilters $filters): array
    {
        $thresholds = ScreenHealth::thresholds($filters->team);
        $screens = Screen::query()
            ->forTeam($filters->team)
            ->when(is_int($filters->filters['screen_id'] ?? null), fn (Builder $query) => $query->whereKey($filters->filters['screen_id']))
            ->when(is_int($filters->filters['location_id'] ?? null), fn (Builder $query) => $query->where('location_id', $filters->filters['location_id']))
            ->get();

        $snapshots = $screens->map(fn (Screen $screen) => ScreenHealth::snapshot($screen, $thresholds));
        $online = $snapshots->where('status', 'online')->count();
        $warning = $snapshots->where('status', 'warning')->count();
        $offline = $snapshots->where('status', 'offline')->count();
        $disabled = $snapshots->where('status', 'disabled')->count();

        $storageWarnings = $screens->filter(function (Screen $screen) {
            return ClassifyPlayerAnalytics::storageWarning($screen->storage_available, $screen->storage_total);
        })->count();

        $events = $this->analyticsQuery($filters);
        $versions = $this->versions($filters, $screens, $events);
        $uptime = $this->uptime($filters, $screens, $events);

        $playback = $this->playbackQuery($filters);
        $playTotals = (clone $playback)
            ->toBase()
            ->selectRaw('count(*) as plays')
            ->selectRaw('coalesce(sum(duration_ms), 0) as duration_ms')
            ->selectRaw('count(distinct screen_id) as screens')
            ->selectRaw('count(distinct location_id) as locations')
            ->first();

        $topContent = (clone $playback)
            ->toBase()
            ->selectRaw("coalesce(title, content_id, 'Untitled') as label")
            ->selectRaw('count(*) as value')
            ->groupBy(DB::raw("coalesce(title, content_id, 'Untitled')"))
            ->orderByDesc('value')
            ->limit(8)
            ->get();

        $dailyPlays = (clone $playback)
            ->toBase()
            ->selectRaw('date(played_at) as label')
            ->selectRaw('count(*) as value')
            ->groupBy(DB::raw('date(played_at)'))
            ->orderBy('label')
            ->get();

        $opCounts = (clone $events)
            ->toBase()
            ->selectRaw('type')
            ->selectRaw('count(*) as value')
            ->groupBy('type')
            ->pluck('value', 'type');

        $dailyOps = (clone $events)
            ->toBase()
            ->selectRaw('date(recorded_at) as label')
            ->selectRaw("sum(case when type = 'download_failure' then 1 else 0 end) as downloads")
            ->selectRaw("sum(case when type = 'crash' then 1 else 0 end) as crashes")
            ->selectRaw("sum(case when type = 'sync_failure' then 1 else 0 end) as sync_failures")
            ->selectRaw("sum(case when type = 'device_error' then 1 else 0 end) as device_errors")
            ->selectRaw("sum(case when type = 'command_failure' then 1 else 0 end) as command_failures")
            ->groupBy(DB::raw('date(recorded_at)'))
            ->orderBy('label')
            ->get();

        return [
            'screens' => [
                'online' => $online,
                'warning' => $warning,
                'offline' => $offline,
                'disabled' => $disabled,
                'uptime_percent' => $uptime,
                'storage_warnings' => $storageWarnings,
                'versions' => $this->bars($versions),
                'status' => [
                    ['label' => 'Online', 'value' => $online],
                    ['label' => 'Warning', 'value' => $warning],
                    ['label' => 'Offline', 'value' => $offline],
                    ['label' => 'Disabled', 'value' => $disabled],
                ],
            ],
            'content' => [
                'plays' => (int) ($playTotals->plays ?? 0),
                'duration_ms' => (int) ($playTotals->duration_ms ?? 0),
                'screens_reached' => (int) ($playTotals->screens ?? 0),
                'locations_reached' => (int) ($playTotals->locations ?? 0),
                'top' => $this->bars($topContent),
                'daily' => $this->bars($dailyPlays),
            ],
            'operations' => [
                'failed_downloads' => (int) ($opCounts[AnalyticsEventType::DownloadFailure->value] ?? 0),
                'crashes' => (int) ($opCounts[AnalyticsEventType::Crash->value] ?? 0),
                'sync_failures' => (int) ($opCounts[AnalyticsEventType::SyncFailure->value] ?? 0),
                'device_errors' => (int) ($opCounts[AnalyticsEventType::DeviceError->value] ?? 0),
                'command_failures' => (int) ($opCounts[AnalyticsEventType::CommandFailure->value] ?? 0),
                'daily' => array_values($dailyOps->map(fn (object $row) => [
                    'label' => (string) $row->label,
                    'downloads' => (int) $row->downloads,
                    'crashes' => (int) $row->crashes,
                    'sync' => (int) $row->sync_failures,
                    'errors' => (int) $row->device_errors,
                    'commands' => (int) $row->command_failures,
                ])->all()),
            ],
        ];
    }

    /**
     * @return Builder<PlayerAnalyticsEvent>
     */
    protected function analyticsQuery(AnalyticsFilters $filters): Builder
    {
        return PlayerAnalyticsEvent::query()
            ->forTeam($filters->team)
            ->whereBetween('recorded_at', [$filters->from, $filters->until])
            ->when(is_int($filters->filters['screen_id'] ?? null), fn (Builder $query) => $query->where('screen_id', $filters->filters['screen_id']))
            ->when(is_int($filters->filters['location_id'] ?? null), fn (Builder $query) => $query->where('location_id', $filters->filters['location_id']));
    }

    /**
     * @return Builder<PlayerPlaybackEvent>
     */
    protected function playbackQuery(AnalyticsFilters $filters): Builder
    {
        return PlayerPlaybackEvent::query()
            ->forTeam($filters->team)
            ->whereBetween('played_at', [$filters->from, $filters->until])
            ->when(is_int($filters->filters['screen_id'] ?? null), fn (Builder $query) => $query->where('screen_id', $filters->filters['screen_id']))
            ->when(is_int($filters->filters['location_id'] ?? null), fn (Builder $query) => $query->where('location_id', $filters->filters['location_id']));
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @param  Builder<PlayerAnalyticsEvent>  $events
     * @return Collection<int, mixed>
     */
    protected function versions(AnalyticsFilters $filters, Collection $screens, Builder $events): Collection
    {
        $historical = (clone $events)
            ->toBase()
            ->whereNotNull('player_version')
            ->selectRaw('player_version as label')
            ->selectRaw('count(distinct screen_id) as value')
            ->groupBy('player_version')
            ->orderByDesc('value')
            ->get();

        if ($historical->isNotEmpty()) {
            return $historical;
        }

        return $screens
            ->groupBy(fn (Screen $screen) => $screen->app_version ?: 'Unknown')
            ->map(fn (Collection $group, string $label) => (object) [
                'label' => $label,
                'value' => $group->count(),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @param  Builder<PlayerAnalyticsEvent>  $events
     */
    protected function uptime(AnalyticsFilters $filters, Collection $screens, Builder $events): float
    {
        if ($screens->isEmpty()) {
            return 0.0;
        }

        $days = $filters->dayCount();
        $seen = (clone $events)
            ->toBase()
            ->selectRaw('screen_id')
            ->selectRaw('count(distinct date(recorded_at)) as days')
            ->groupBy('screen_id')
            ->pluck('days', 'screen_id');

        $total = $screens->sum(function (Screen $screen) use ($seen, $days) {
            return min($days, (int) ($seen[$screen->id] ?? 0)) / $days;
        });

        return round(($total / $screens->count()) * 100, 1);
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<array{label: string, value: int}>
     */
    protected function bars(Collection $rows): array
    {
        return array_values($rows->map(fn (object $row) => [
            'label' => (string) ($row->label ?? 'Unknown'),
            'value' => (int) ($row->value ?? 0),
        ])->all());
    }
}
