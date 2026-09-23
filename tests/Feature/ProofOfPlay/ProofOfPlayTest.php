<?php

namespace Tests\Feature\ProofOfPlay;

use App\Enums\PlaybackStatus;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Location;
use App\Models\PlayerPlaybackEvent;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProofOfPlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_records_started_ended_and_status(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $started = now()->subSeconds(20);
        $ended = now()->subSeconds(5);

        $this->withToken($token)
            ->postJson('/api/player/v1/playback', [
                'item_key' => 'item:42',
                'content_id' => 'item:42',
                'title' => 'Lobby Loop',
                'duration_ms' => 15000,
                'started_at' => $started->toIso8601String(),
                'ended_at' => $ended->toIso8601String(),
                'status' => PlaybackStatus::Completed->value,
            ])
            ->assertCreated();

        $event = PlayerPlaybackEvent::query()->firstOrFail();
        $this->assertSame($screen->id, $event->screen_id);
        $this->assertSame($screen->team_id, $event->team_id);
        $this->assertSame('item:42', $event->content_id);
        $this->assertSame(PlaybackStatus::Completed, $event->status);
        $this->assertEquals($started->timestamp, $event->started_at?->timestamp);
        $this->assertEquals($ended->timestamp, $event->ended_at?->timestamp);
    }

    public function test_foreign_playlist_ids_are_not_stored(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $foreign = Playlist::factory()->create();

        $this->withToken($token)
            ->postJson('/api/player/v1/playback', [
                'title' => 'Local clip',
                'playlist_id' => $foreign->id,
                'duration_ms' => 10000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('player_playback_events', [
            'title' => 'Local clip',
            'playlist_id' => null,
            'team_id' => $screen->team_id,
        ]);
    }

    public function test_owners_can_view_and_filter_proof_of_play(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create(['team_id' => $user->currentTeam->id]);
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'location_id' => $location->id,
            'name' => 'Lobby Screen',
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Morning',
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $user->currentTeam->id,
            'playlist_id' => $playlist->id,
            'name' => 'Reception',
        ]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $user->currentTeam->id,
            'screen_id' => $screen->id,
            'location_id' => $location->id,
            'playlist_id' => $playlist->id,
            'channel_id' => $channel->id,
            'title' => 'Welcome Video',
            'content_id' => 'item:7',
            'played_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'duration_ms' => 30000,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('proof-of-play.index', [
                'current_team' => $user->currentTeam,
                'location_id' => $location->id,
                'group' => 'location',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/proof-of-play')
                ->where('totals.plays', 1)
                ->where('grouped.0.label', $location->name)
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Welcome Video'));
    }

    public function test_proof_of_play_survives_a_deleted_screen(): void
    {
        $user = User::factory()->create();
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Retired Kiosk',
        ]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $user->currentTeam->id,
            'screen_id' => $screen->id,
            'title' => 'Last play',
            'played_at' => now()->subHour(),
        ]);
        $screen->delete();

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('proof-of-play.index', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/proof-of-play')
                ->where('events.data.0.screen', 'Retired Kiosk')
                ->where('events.data.0.title', 'Last play'));
    }

    public function test_members_can_view_proof_of_play(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        $screen = Screen::factory()->create(['team_id' => $team->id]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $team->id,
            'screen_id' => $screen->id,
            'title' => 'Member visible',
        ]);

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('proof-of-play.index', $team))
            ->assertOk();
    }

    public function test_user_cannot_view_another_teams_proof_of_play(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $userB->currentTeam->id]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'screen_id' => $screen->id,
            'title' => 'Secret play',
        ]);

        $this->actingAs($userA)
            ->get(route('proof-of-play.index', $userB->currentTeam))
            ->assertForbidden();
    }

    public function test_csv_export_is_limited_to_the_team(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $screen = Screen::factory()->create(['team_id' => $user->currentTeam->id]);
        $otherScreen = Screen::factory()->create(['team_id' => $foreign->currentTeam->id]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $user->currentTeam->id,
            'screen_id' => $screen->id,
            'title' => 'Team Clip',
        ]);
        PlayerPlaybackEvent::factory()->create([
            'team_id' => $foreign->currentTeam->id,
            'screen_id' => $otherScreen->id,
            'title' => 'Foreign Clip',
        ]);

        $csv = $this->actingAs($user)
            ->get(route('proof-of-play.export', $user->currentTeam))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Team Clip', $csv);
        $this->assertStringNotContainsString('Foreign Clip', $csv);
        $this->assertStringContainsString('content_id', $csv);
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreen(): array
    {
        $user = User::factory()->create();
        $plain = 'proof-device-token-'.fake()->uuid();
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

        return [$screen, $plain];
    }
}
