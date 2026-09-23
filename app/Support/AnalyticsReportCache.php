<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the analytics report aggregates. Keyed by team and
 * filter set, versioned per team so new telemetry invalidates cached
 * reports without cache tags.
 */
class AnalyticsReportCache
{
    public const SCOPE = 'analytics-report';

    /**
     * @template TCacheValue
     *
     * @param  array<string, mixed>  $filters
     * @param  \Closure(): TCacheValue  $report
     * @return TCacheValue
     */
    public static function remember(int $teamId, array $filters, \Closure $report): mixed
    {
        $version = ScopedCacheVersion::get(self::SCOPE, $teamId);
        $key = 'analytics:report:'.$teamId.':v'.$version.':'.sha1((string) json_encode($filters));

        return Cache::remember($key, self::ttl(), $report);
    }

    public static function bumpTeam(int $teamId): void
    {
        ScopedCacheVersion::bump(self::SCOPE, $teamId);
    }

    public static function ttl(): int
    {
        return max(15, (int) config('signage.analytics.report_cache_seconds', 60));
    }
}
