<?php

namespace Tests\Feature\Player;

use App\Enums\ScreenStatus;
use App\Models\PlayerPlaybackEvent;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerOfflineTest extends TestCase
{
    use RefreshDatabase;

    public function test_telemetry_requires_a_device_token(): void
    {
        $this->postJson('/api/player/v1/telemetry', ['items' => []])->assertUnauthorized();
    }

    public function test_reconnect_flushes_queued_playback_and_heartbeat(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playedAt = now()->subMinutes(18);

        $this->withToken($token)
            ->postJson('/api/player/v1/telemetry', [
                'items' => [
                    [
                        'type' => 'playback',
                        'payload' => [
                            'title' => 'Queued Clip',
                            'duration_ms' => 15000,
                            'played_at' => $playedAt->toIso8601String(),
                        ],
                    ],
                    [
                        'type' => 'heartbeat',
                        'payload' => [
                            'player_version' => 'web-1.1',
                            'current_content' => 'Queued Clip',
                            'network_status' => 'offline',
                            'playing_offline' => true,
                            'manifest_version' => 43,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'heartbeats' => 1,
                'playback' => 1,
            ]);

        $screen->refresh();
        $this->assertSame(ScreenStatus::Online, $screen->status);
        $this->assertSame('web-1.1', $screen->app_version);
        $this->assertTrue((bool) $screen->metadata['player']['playing_offline']);
        $this->assertSame(43, $screen->metadata['player']['manifest_version']);
        $this->assertSame('Queued Clip', $screen->metadata['player']['current_content']);

        $this->assertDatabaseHas('player_playback_events', [
            'screen_id' => $screen->id,
            'team_id' => $screen->team_id,
            'title' => 'Queued Clip',
            'duration_ms' => 15000,
        ]);

        $event = PlayerPlaybackEvent::query()->where('screen_id', $screen->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals($playedAt->timestamp, $event->played_at->timestamp);
    }

    public function test_heartbeat_stores_offline_playback_state(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'player_version' => 'web-1.1',
                'current_content' => 'Reception Wall',
                'network_status' => 'offline',
                'playing_offline' => true,
                'manifest_version' => 12,
            ])
            ->assertOk();

        $screen->refresh();
        $this->assertSame(12, $screen->metadata['player']['manifest_version']);
        $this->assertTrue((bool) $screen->metadata['player']['playing_offline']);
    }

    public function test_invalid_telemetry_item_is_rejected(): void
    {
        [, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/telemetry', [
                'items' => [
                    ['type' => 'playback', 'payload' => ['duration_ms' => -4]],
                ],
            ])
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'player-device-token';
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain];
    }
}
