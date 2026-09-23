<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Monotonic per-team cache versions so cached payloads can be invalidated
 * without cache tags (which the database and array stores do not support).
 *
 * Readers embed the current version in their cache key; writers bump the
 * version and stale keys expire on their own via TTL.
 */
final class ScopedCacheVersion
{
    private const TTL = 2592000; // 30 days

    public static function get(string $scope, int $teamId): int
    {
        return (int) Cache::remember(self::key($scope, $teamId), self::TTL, fn () => 1);
    }

    public static function bump(string $scope, int $teamId): void
    {
        $key = self::key($scope, $teamId);

        if (Cache::add($key, 1, self::TTL)) {
            return;
        }

        Cache::increment($key);
    }

    public static function key(string $scope, int $teamId): string
    {
        return 'cache-version:'.$scope.':'.$teamId;
    }
}
