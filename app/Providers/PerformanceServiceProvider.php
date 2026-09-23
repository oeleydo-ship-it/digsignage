<?php

namespace App\Providers;

use App\Events\PlayerManifestUpdated;
use App\Models\Announcement;
use App\Models\Channel;
use App\Models\ChannelZone;
use App\Models\Design;
use App\Models\Emergency;
use App\Models\EmergencyTarget;
use App\Models\InAppNotification;
use App\Models\Media;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\PlayerAnalyticsEvent;
use App\Models\PlayerPlaybackEvent;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Schedule;
use App\Models\ScheduleTarget;
use App\Models\Screen;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Template;
use App\Support\AnalyticsReportCache;
use App\Support\BillingCatalog;
use App\Support\PlayerManifestCache;
use App\Support\TeamQuota;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Central cache-invalidation wiring for the performance layer.
 *
 * Cache tags require Redis, so invalidation is explicit: model events forget
 * or bump the keys that depend on them. Listeners must stay cheap — several
 * fire on hot write paths such as player heartbeats.
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Fields that change what a player manifest contains when edited on a
     * screen. Heartbeat-only updates (last_seen_at, storage, player
     * telemetry metadata) must not invalidate the manifest cache.
     *
     * @var list<string>
     */
    private const MANIFEST_SCREEN_FIELDS = [
        'name',
        'orientation',
        'resolution_width',
        'resolution_height',
        'timezone',
        'location_id',
        'current_channel_id',
    ];

    public function boot(): void
    {
        $this->invalidatePlanCatalog();
        $this->invalidateTeamUsage();
        $this->invalidateInboxCounts();
        $this->invalidateAnnouncements();
        $this->invalidateAnalyticsReports();
        $this->invalidatePlayerManifests();
    }

    /**
     * Plan rows are read on nearly every quota and billing check.
     */
    protected function invalidatePlanCatalog(): void
    {
        $forget = function (Plan $plan): void {
            BillingCatalog::forgetPlanCache($plan->key);
        };

        Plan::saved($forget);
        Plan::deleted($forget);
    }

    /**
     * Dashboard / billing usage summaries are cached briefly per team.
     */
    protected function invalidateTeamUsage(): void
    {
        $forgetTeam = function (Model $model): void {
            $teamId = $model->getAttribute('team_id');

            if (is_int($teamId) && $teamId > 0) {
                TeamQuota::forgetUsageCache($teamId);
            }
        };

        foreach ([Media::class, TeamInvitation::class, Membership::class] as $model) {
            $model::saved($forgetTeam);
            $model::deleted($forgetTeam);
        }

        Screen::created($forgetTeam);
        Screen::deleted($forgetTeam);
        Screen::restored($forgetTeam);
    }

    /**
     * The unread inbox badge is cached per user and team.
     */
    protected function invalidateInboxCounts(): void
    {
        $forget = function (InAppNotification $notification): void {
            Cache::forget('notifications:unread:'.$notification->team_id.':'.$notification->user_id);
        };

        InAppNotification::saved($forget);
        InAppNotification::deleted($forget);
    }

    /**
     * Announcements are shared on every Inertia response.
     */
    protected function invalidateAnnouncements(): void
    {
        $forget = fn (): bool => Cache::forget('platform:announcements:published');

        Announcement::saved($forget);
        Announcement::deleted($forget);
    }

    /**
     * Analytics reports are cached per team and filter set; new telemetry
     * bumps the team's report version.
     */
    protected function invalidateAnalyticsReports(): void
    {
        $bump = function (Model $event): void {
            $teamId = $event->getAttribute('team_id');

            if (is_int($teamId) && $teamId > 0) {
                AnalyticsReportCache::bumpTeam($teamId);
            }
        };

        PlayerAnalyticsEvent::created($bump);
        PlayerPlaybackEvent::created($bump);
    }

    /**
     * Any content or targeting write bumps the team's manifest version so
     * players pick up changes on their next poll.
     */
    protected function invalidatePlayerManifests(): void
    {
        $bump = function (Model $model): void {
            $teamId = $model->getAttribute('team_id');

            if (is_int($teamId)) {
                PlayerManifestCache::bumpTeam($teamId);
            }
        };

        foreach ([
            Schedule::class,
            ScheduleTarget::class,
            Channel::class,
            ChannelZone::class,
            Playlist::class,
            PlaylistItem::class,
            Design::class,
            Template::class,
            Media::class,
            Emergency::class,
            EmergencyTarget::class,
        ] as $model) {
            $model::saved($bump);
            $model::deleted($bump);
        }

        Screen::saved(function (Screen $screen): void {
            if ($screen->wasChanged(self::MANIFEST_SCREEN_FIELDS) || $this->fallbackImageChanged($screen)) {
                PlayerManifestCache::bumpTeam($screen->team_id);

                if (DB::transactionLevel() > 0) {
                    DB::afterCommit(fn () => PlayerManifestCache::bumpTeam($screen->team_id));
                }

                if ($screen->isPaired()) {
                    event(new PlayerManifestUpdated($screen->device_uuid));
                }
            }
        });
        Screen::deleted($bump);

        Team::saved(function (Team $team): void {
            if ($team->wasChanged('settings')) {
                PlayerManifestCache::bumpTeam($team->id);
            }
        });

    }

    /**
     * The per-screen fallback image lives in metadata and is part of the
     * manifest fallback payload.
     */
    protected function fallbackImageChanged(Screen $screen): bool
    {
        if (! $screen->wasChanged('metadata')) {
            return false;
        }

        $original = $screen->getRawOriginal('metadata');
        $before = is_string($original) ? json_decode($original, true) : null;
        $beforeImage = is_array($before) ? ($before['fallback_image'] ?? null) : null;
        $after = is_array($screen->metadata) ? ($screen->metadata['fallback_image'] ?? null) : null;

        return $beforeImage !== $after;
    }
}
