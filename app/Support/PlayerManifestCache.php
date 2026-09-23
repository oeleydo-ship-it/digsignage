<?php

namespace App\Support;

use App\Models\Screen;
use Illuminate\Support\Facades\Cache;

/**
 * Caches built player manifests per screen so the every-poll manifest
 * endpoint (and per-asset validation) does not rebuild the schedule,
 * channel, playlist, and widget graph on each request.
 *
 * Invalidation is version-based: any write to schedules, channels,
 * playlists, designs, templates, media, emergencies, or screen playback
 * fields bumps the team's manifest version and stale entries expire.
 */
class PlayerManifestCache
{
    public const SCOPE = 'player-manifest';

    /**
     * @template TCacheValue
     *
     * @param  \Closure(): TCacheValue  $build
     * @return TCacheValue
     */
    public static function remember(Screen $screen, \Closure $build): mixed
    {
        $version = ScopedCacheVersion::get(self::SCOPE, $screen->team_id);
        $key = "player:manifest:{$screen->id}:v{$version}";

        return Cache::remember($key, self::ttl(), $build);
    }

    public static function bumpTeam(?int $teamId): void
    {
        if ($teamId === null || $teamId < 1) {
            return;
        }

        ScopedCacheVersion::bump(self::SCOPE, $teamId);
    }

    public static function ttl(): int
    {
        return max(5, (int) config('signage.player.manifest_cache_seconds', 20));
    }
}
