<?php

namespace App\Actions\Player;

use App\Actions\Analytics\RecordScreenAnalytics;
use App\Actions\Notifications\EvaluatePlayerAlerts;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Enums\ScreenStatus;
use App\Enums\WebhookEvent;
use App\Models\Screen;

class RecordPlayerHeartbeat
{
    public function __construct(
        protected RecordScreenAnalytics $recordScreenAnalytics,
        protected EvaluatePlayerAlerts $evaluatePlayerAlerts,
    ) {}

    /**
     * Apply heartbeat telemetry to a paired screen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Screen $screen, array $payload, ?string $ip = null): void
    {
        $previousStatus = $screen->status;
        $metadata = is_array($screen->metadata) ? $screen->metadata : [];
        $metadata['player'] = [
            'current_content' => $payload['current_content'] ?? null,
            'memory_usage' => $payload['memory_usage'] ?? null,
            'cpu_usage' => $payload['cpu_usage'] ?? null,
            'network_status' => $payload['network_status'] ?? null,
            'last_error' => $payload['last_error'] ?? null,
            'manifest_version' => $payload['manifest_version'] ?? null,
            'playing_offline' => (bool) ($payload['playing_offline'] ?? false),
            'heartbeat_at' => now()->toIso8601String(),
        ];

        $screen->forceFill([
            'last_seen_at' => now(),
            'app_version' => $payload['player_version'] ?? $screen->app_version,
            'ip_address' => $ip ?? $screen->ip_address,
            'storage_available' => $payload['storage_free'] ?? $screen->storage_available,
            'storage_total' => $payload['storage_total'] ?? $screen->storage_total,
            'status' => ScreenStatus::Online,
            'metadata' => $metadata,
        ])->save();

        $this->recordScreenAnalytics->fromHeartbeat($screen, $payload);
        $this->evaluatePlayerAlerts->fromHeartbeat($screen, $previousStatus, $payload);

        if ($previousStatus === ScreenStatus::Offline && $screen->status !== ScreenStatus::Offline) {
            $screen->loadMissing('team');
            app(DispatchPartnerWebhook::class)->handle(
                $screen->team,
                WebhookEvent::ScreenOnline,
                ['screen_id' => $screen->id, 'name' => $screen->name, 'status' => $screen->status->value],
            );
        }
    }
}
