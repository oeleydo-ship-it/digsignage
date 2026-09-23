<?php

namespace App\Actions\Analytics;

use App\Enums\AnalyticsEventType;
use App\Models\PlayerAnalyticsEvent;
use App\Models\Screen;
use App\Support\ClassifyPlayerAnalytics;
use Illuminate\Support\Facades\Cache;

class RecordScreenAnalytics
{
    /**
     * Persist a sampled heartbeat and any operational signal from player telemetry.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromHeartbeat(Screen $screen, array $payload): ?PlayerAnalyticsEvent
    {
        $type = ClassifyPlayerAnalytics::fromHeartbeat($payload);
        $sampleSeconds = max(60, (int) config('signage.analytics.heartbeat_sample_seconds', 300));

        if ($type === AnalyticsEventType::Heartbeat && ! Cache::add('analytics:heartbeat:'.$screen->id, 1, $sampleSeconds)) {
            return null;
        }

        return $this->store(
            $screen,
            $type,
            is_string($payload['last_error'] ?? null) ? $payload['last_error'] : null,
            is_string($payload['player_version'] ?? null) ? $payload['player_version'] : $screen->app_version,
            $payload['storage_free'] ?? $screen->storage_available,
            $payload['storage_total'] ?? $screen->storage_total,
        );
    }

    public function commandFailure(Screen $screen, ?string $message = null): PlayerAnalyticsEvent
    {
        return $this->store(
            $screen,
            AnalyticsEventType::CommandFailure,
            $message,
            $screen->app_version,
            $screen->storage_available,
            $screen->storage_total,
        );
    }

    protected function store(
        Screen $screen,
        AnalyticsEventType $type,
        ?string $message,
        ?string $version,
        mixed $storageFree,
        mixed $storageTotal,
    ): PlayerAnalyticsEvent {
        $free = is_numeric($storageFree) ? (int) $storageFree : null;
        $total = is_numeric($storageTotal) ? (int) $storageTotal : null;

        return PlayerAnalyticsEvent::query()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'location_id' => $screen->location_id,
            'type' => $type,
            'message' => $message !== null ? mb_substr($message, 0, 255) : null,
            'player_version' => $version !== null ? mb_substr($version, 0, 64) : null,
            'storage_free' => $free,
            'storage_total' => $total,
            'storage_warning' => ClassifyPlayerAnalytics::storageWarning($free, $total),
            'recorded_at' => now(),
        ]);
    }
}
