<?php

namespace Tests\Feature\Analytics;

use App\Enums\AnalyticsEventType;
use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\PlaybackStatus;
use App\Enums\TeamRole;
use App\Models\DeviceCommand;
use App\Models\Location;
use App\Models\PlayerAnalyticsEvent;
use App\Models\PlayerPlaybackEvent;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_errors_are_classified_into_analytics_events(): void
    {
        Cache::flush();
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'player_version' => 'web-2.0',
                'last_error' => 'Asset checksum failed during download',
            ])
            ->assertOk();

        $this->assertDatabaseHas('player_analytics_events', [
            'screen_id' => $screen->id,
            'team_id' => $screen->team_id,
            'type' => AnalyticsEventType::DownloadFailure->value,
            'player_version' => 'web-2.0',
        ]);
    }

    public function test_offline_playback_is_recorded_as_a_sync_failure(): void
    {
        Cache::flush();
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'playing_offline' => true,
                'network_status' => 'offline',
            ])
            ->assertOk();

        $this->assertDatabaseHas('player_analytics_events', [
            'screen_id' => $screen->id,
            'type' => AnalyticsEventType::SyncFailure->value,
        ]);
    }

    public function test_failed_commands_create_operational_events(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $command = DeviceCommand::factory()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'command' => DeviceCommandType::Refresh,
            'status' => DeviceCommandStatus::Sent,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken($token)
            ->postJson('/api/player/v1/commands/'.$command->id.'/complete', [
                'status' => 'failed',
                'result' => ['error' => 'Player crashed while refreshing'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('player_analytics_events', [
            'screen_id' => $screen->id,
            'type' => AnalyticsEventType::CommandFailure->value,
            'message' => 'Player crashed while refreshing',
        ]);
    }

    public function test_analytics_dashboard_is_scoped_to_the_team(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $location = Location::factory()->create(['team_id' => $user->currentTeam->id]);
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'location_id' => $location->id,
            'app_version' => 'web-1.4',
            'last_seen_at' => now(),
        ]);
        $other = Screen::factory()->create(['team_id' => $foreign->currentTeam->id]);

        PlayerPlaybackEvent::factory()->create([
            'team_id' => $user->currentTeam->id,
            'screen_id' => $screen->id,
            'location_id' => $location->id,
            'title' => 'Lobby Loop',
            'duration_ms' => 20000,
            'status' => PlaybackStatus::Completed,
            'played_at' => now()->subHour(),
            'started_at' => now()->subHour(),
        ]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $foreign->currentTeam->id,
            'screen_id' => $other->id,
            'title' => 'Secret Clip',
            'played_at' => now()->subHour(),
        ]);
        PlayerAnalyticsEvent::factory()->create([
            'team_id' => $user->currentTeam->id,
            'screen_id' => $screen->id,
            'type' => AnalyticsEventType::Crash,
            'message' => 'Uncaught exception',
            'recorded_at' => now()->subHour(),
        ]);
        PlayerAnalyticsEvent::factory()->create([
            'team_id' => $foreign->currentTeam->id,
            'screen_id' => $other->id,
            'type' => AnalyticsEventType::Crash,
            'recorded_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('analytics.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analytics/index')
                ->where('report.content.plays', 1)
                ->where('report.content.screens_reached', 1)
                ->where('report.content.locations_reached', 1)
                ->where('report.operations.crashes', 1)
                ->where('report.content.top.0.label', 'Lobby Loop'));
    }

    public function test_members_can_view_analytics(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('analytics.index', $team))
            ->assertOk();
    }

    public function test_user_cannot_view_another_teams_analytics(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->get(route('analytics.index', $userB->currentTeam))
            ->assertForbidden();
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'analytics-device-'.fake()->uuid();
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain];
    }
}
