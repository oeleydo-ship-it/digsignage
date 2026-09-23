<?php

namespace Tests\Feature\Signage;

use App\Actions\Signage\EvaluateScreenHealth;
use App\Enums\ScreenStatus;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_marks_a_screen_healthy(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'player_version' => 'web-1.1',
                'current_content' => 'Lobby Welcome',
                'storage_free' => 2048,
                'storage_total' => 8192,
                'last_error' => null,
            ])
            ->assertOk();

        $screen->refresh();
        $this->assertSame(ScreenStatus::Online, $screen->status);
        $this->assertSame('Lobby Welcome', $screen->metadata['player']['current_content']);
        $this->assertSame(2048, $screen->storage_available);
        $this->assertSame('web-1.1', $screen->app_version);
    }

    public function test_stale_heartbeats_move_through_warning_then_offline(): void
    {
        [$screen] = $this->pairedScreen();
        $screen->forceFill([
            'status' => ScreenStatus::Online,
            'last_seen_at' => now()->subMinutes(3),
        ])->save();

        app(EvaluateScreenHealth::class)->handle($screen->team);

        $this->assertSame(ScreenStatus::Warning, $screen->fresh()->status);

        $screen->forceFill(['last_seen_at' => now()->subMinutes(6)])->save();
        app(EvaluateScreenHealth::class)->handle($screen->team);

        $this->assertSame(ScreenStatus::Offline, $screen->fresh()->status);
    }

    public function test_team_thresholds_override_configured_defaults(): void
    {
        [$screen, , $user] = $this->pairedScreen();
        $screen->forceFill([
            'status' => ScreenStatus::Online,
            'last_seen_at' => now()->subSeconds(90),
        ])->save();

        $this->actingAs($user)
            ->patch(route('monitoring.update', $user->currentTeam), [
                'healthy_seconds' => 60,
                'warning_seconds' => 120,
            ])
            ->assertRedirect();

        $this->assertSame(60, $user->currentTeam->fresh()->settings['monitoring']['healthy_seconds']);
        $this->assertSame(ScreenStatus::Warning, $screen->fresh()->status);
    }

    public function test_disabled_screens_are_not_reevaluated(): void
    {
        [$screen] = $this->pairedScreen();
        $screen->forceFill([
            'status' => ScreenStatus::Disabled,
            'last_seen_at' => now()->subHours(2),
        ])->save();

        app(EvaluateScreenHealth::class)->handle($screen->team);

        $this->assertSame(ScreenStatus::Disabled, $screen->fresh()->status);
    }

    public function test_monitoring_page_shows_telemetry_for_the_current_team_only(): void
    {
        [$screen, , $user] = $this->pairedScreen();
        $channel = Channel::factory()->create(['team_id' => $screen->team_id, 'name' => 'Lobby']);
        $screen->forceFill([
            'current_channel_id' => $channel->id,
            'last_seen_at' => now(),
            'app_version' => 'web-1.1',
            'metadata' => ['player' => ['current_content' => 'Welcome', 'last_error' => null]],
        ])->save();

        $foreign = User::factory()->create();
        Screen::factory()->paired()->create([
            'team_id' => $foreign->currentTeam->id,
            'name' => 'Secret Wall',
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('monitoring.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('signage/monitoring/index')
                ->has('screens', 1)
                ->where('screens.0.name', $screen->name)
                ->where('screens.0.current_channel', 'Lobby')
                ->where('screens.0.current_content', 'Welcome'));
    }

    public function test_members_cannot_change_monitoring_thresholds(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->patch(route('monitoring.update', $team), [
                'healthy_seconds' => 60,
                'warning_seconds' => 180,
            ])
            ->assertForbidden();
    }

    public function test_dashboard_includes_health_counts(): void
    {
        [$screen, , $user] = $this->pairedScreen();
        $screen->forceFill([
            'status' => ScreenStatus::Online,
            'last_seen_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('dashboard', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('monitoring.counts.healthy', 1));
    }

    /**
     * @return array{0: Screen, 1: string, 2: User}
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

        return [$screen, $plain, $user];
    }
}
