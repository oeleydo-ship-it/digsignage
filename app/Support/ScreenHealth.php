<?php

namespace App\Support;

use App\Data\MonitoringThresholds;
use App\Enums\ScreenStatus;
use App\Models\Screen;
use App\Models\Team;
use DateTimeInterface;

class ScreenHealth
{
    /**
     * Resolve monitoring thresholds for a team without hard-coding them.
     */
    public static function thresholds(?Team $team = null): MonitoringThresholds
    {
        $settings = is_array($team?->settings) ? $team->settings : [];
        $monitoring = is_array($settings['monitoring'] ?? null) ? $settings['monitoring'] : [];

        $healthy = (int) ($monitoring['healthy_seconds'] ?? config('signage.monitoring.healthy_seconds'));
        $warning = (int) ($monitoring['warning_seconds'] ?? config('signage.monitoring.warning_seconds'));

        $healthy = max(30, $healthy);
        $warning = max($healthy + 30, $warning);

        return new MonitoringThresholds($healthy, $warning);
    }

    /**
     * Map last-seen age onto the configured healthy / warning / offline bands.
     */
    public static function statusFor(Screen $screen, MonitoringThresholds $thresholds, ?DateTimeInterface $now = null): ScreenStatus
    {
        if ($screen->status === ScreenStatus::Disabled) {
            return ScreenStatus::Disabled;
        }

        if ($screen->last_seen_at === null) {
            return ScreenStatus::Offline;
        }

        $age = ($now ?? now())->getTimestamp() - $screen->last_seen_at->getTimestamp();

        if ($age < $thresholds->healthySeconds) {
            return ScreenStatus::Online;
        }

        if ($age < $thresholds->warningSeconds) {
            return ScreenStatus::Warning;
        }

        return ScreenStatus::Offline;
    }

    /**
     * Display payload for dashboards and the screens index.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Screen $screen, ?MonitoringThresholds $thresholds = null): array
    {
        $thresholds ??= self::thresholds($screen->team);
        $player = is_array($screen->metadata['player'] ?? null) ? $screen->metadata['player'] : [];
        $health = self::statusFor($screen, $thresholds);

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'status' => $health->value,
            'status_label' => $health === ScreenStatus::Online ? 'Healthy' : $health->label(),
            'paired' => $screen->isPaired(),
            'last_seen_at' => $screen->last_seen_at?->toIso8601String(),
            'current_channel' => $screen->currentChannel?->name,
            'current_content' => $player['current_content'] ?? null,
            'storage_available' => $screen->storage_available,
            'storage_total' => $screen->storage_total,
            'app_version' => $screen->app_version,
            'last_error' => $player['last_error'] ?? null,
            'network_status' => $player['network_status'] ?? null,
            'memory_usage' => $player['memory_usage'] ?? null,
            'cpu_usage' => $player['cpu_usage'] ?? null,
            'playing_offline' => (bool) ($player['playing_offline'] ?? false),
        ];
    }
}
