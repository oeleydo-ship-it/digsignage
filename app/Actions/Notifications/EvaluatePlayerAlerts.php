<?php

namespace App\Actions\Notifications;

use App\Enums\AnalyticsEventType;
use App\Enums\ScreenStatus;
use App\Enums\SignageAlert;
use App\Models\NotificationSetting;
use App\Models\Screen;
use App\Support\ClassifyPlayerAnalytics;
use App\Support\PlayerVersion;
use Illuminate\Support\Facades\Cache;

class EvaluatePlayerAlerts
{
    public function __construct(protected DispatchSignageAlert $alerts) {}

    /**
     * Raise restored / storage / sync / outdated alerts from a heartbeat.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromHeartbeat(Screen $screen, ScreenStatus $previous, array $payload): void
    {
        $team = $screen->team;
        $current = $screen->status;

        if ($previous === ScreenStatus::Offline && in_array($current, [ScreenStatus::Online, ScreenStatus::Warning, ScreenStatus::Updating], true)) {
            Cache::forget('signage-alert:'.$team->id.':screen_offline:'.$screen->id);
            $this->alerts->queue(
                $team,
                SignageAlert::ScreenRestored,
                __('Screen restored: :name', ['name' => $screen->name]),
                __('":name" is sending heartbeats again.', ['name' => $screen->name]),
                ['screen_id' => $screen->id],
                'screen_restored:'.$screen->id,
                60,
            );
        }

        $free = $payload['storage_free'] ?? $screen->storage_available;
        $total = $payload['storage_total'] ?? $screen->storage_total;

        if (ClassifyPlayerAnalytics::storageWarning($free, $total)) {
            $this->alerts->queue(
                $team,
                SignageAlert::StorageLow,
                __('Storage low: :name', ['name' => $screen->name]),
                __('":name" is below the storage warning threshold.', ['name' => $screen->name]),
                ['screen_id' => $screen->id],
                'storage_low:'.$screen->id,
                3600,
            );
        }

        if (ClassifyPlayerAnalytics::fromHeartbeat($payload) === AnalyticsEventType::SyncFailure) {
            $this->alerts->queue(
                $team,
                SignageAlert::SyncFailed,
                __('Synchronization failed: :name', ['name' => $screen->name]),
                __('":name" reported a sync or offline playback problem.', ['name' => $screen->name]),
                ['screen_id' => $screen->id],
                'sync_failed:'.$screen->id,
                900,
            );
        }

        $settings = NotificationSetting::resolveForTeam($team);
        $version = is_string($payload['player_version'] ?? null)
            ? $payload['player_version']
            : $screen->app_version;

        if (PlayerVersion::isOutdated($version, $settings->min_player_version)) {
            $this->alerts->queue(
                $team,
                SignageAlert::PlayerOutdated,
                __('Player outdated: :name', ['name' => $screen->name]),
                __('":name" is running :version, below the required :minimum.', [
                    'name' => $screen->name,
                    'version' => (string) $version,
                    'minimum' => (string) $settings->min_player_version,
                ]),
                ['screen_id' => $screen->id, 'player_version' => $version],
                'player_outdated:'.$screen->id.':'.(string) $version,
                86400,
            );
        }
    }

    public function screenOffline(Screen $screen): void
    {
        $this->alerts->queue(
            $screen->team,
            SignageAlert::ScreenOffline,
            __('Screen offline: :name', ['name' => $screen->name]),
            __('":name" stopped sending heartbeats.', ['name' => $screen->name]),
            ['screen_id' => $screen->id],
            'screen_offline:'.$screen->id,
            86400,
        );
    }
}
