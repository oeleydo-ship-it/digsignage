<?php

namespace Tests\Feature\Channel;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\LiveStreamProtocol;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_playlist_channels(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Morning Playlist',
        ]);

        $this->actingAs($user)
            ->post(route('channels.store', $user->currentTeam), [
                'name' => 'Lobby',
                'type' => ChannelType::Playlist->value,
                'playlist_id' => $playlist->id,
            ])
            ->assertRedirect();

        $channel = Channel::query()->firstOrFail();
        $this->assertSame($user->currentTeam->id, $channel->team_id);
        $this->assertSame(ChannelType::Playlist, $channel->type);
        $this->assertSame($playlist->id, $channel->playlist_id);
        $this->assertSame(ChannelStatus::Draft, $channel->status);
    }

    public function test_advanced_channels_store_zones(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->post(route('channels.store', $user->currentTeam), [
                'name' => 'Reception Wall',
                'type' => ChannelType::Advanced->value,
            ])
            ->assertRedirect();

        $channel = Channel::query()->firstOrFail();
        $this->assertSame(3, $channel->zones()->count());

        $this->actingAs($user)
            ->patch(route('channels.update', [$user->currentTeam, $channel]), [
                'name' => 'Reception Wall',
                'type' => ChannelType::Advanced->value,
                'zones' => [
                    [
                        'name' => 'Main Video',
                        'playlist_id' => $playlist->id,
                        'x' => 0,
                        'y' => 0,
                        'width' => 100,
                        'height' => 70,
                        'z_index' => 1,
                    ],
                    [
                        'name' => 'News Ticker',
                        'x' => 0,
                        'y' => 70,
                        'width' => 70,
                        'height' => 30,
                        'z_index' => 2,
                    ],
                    [
                        'name' => 'Weather',
                        'x' => 70,
                        'y' => 70,
                        'width' => 30,
                        'height' => 30,
                        'z_index' => 3,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('Main Video', $channel->fresh()->zones()->first()?->name);
        $this->assertSame($playlist->id, $channel->fresh()->zones()->first()?->playlist_id);
    }

    public function test_live_channels_store_stream_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('channels.store', $user->currentTeam), [
                'name' => 'IPTV Feed',
                'type' => ChannelType::Live->value,
                'live_protocol' => LiveStreamProtocol::Hls->value,
                'live_url' => 'https://example.com/live/index.m3u8',
                'status' => ChannelStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('channels', [
            'name' => 'IPTV Feed',
            'type' => ChannelType::Live->value,
            'live_protocol' => LiveStreamProtocol::Hls->value,
            'status' => ChannelStatus::Draft->value,
        ]);
    }

    public function test_user_cannot_update_another_teams_channel(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $channel = Channel::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret channel',
        ]);

        $this->actingAs($userA)
            ->patch(route('channels.update', [$userA->currentTeam, $channel]), [
                'name' => 'Stolen',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('channels', [
            'id' => $channel->id,
            'name' => 'Secret channel',
        ]);
    }

    public function test_user_cannot_assign_another_teams_playlist(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $userB->currentTeam->id,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $userA->currentTeam->id,
        ]);

        $this->actingAs($userA)
            ->patch(route('channels.update', [$userA->currentTeam, $channel]), [
                'name' => $channel->name,
                'type' => ChannelType::Playlist->value,
                'playlist_id' => $playlist->id,
            ])
            ->assertSessionHasErrors('playlist_id');
    }

    public function test_members_cannot_create_channels(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('channels.store', $team), [
                'name' => 'No Access',
            ])
            ->assertForbidden();
    }

    public function test_channels_can_be_duplicated_with_zones(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->advanced()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Wall',
        ]);

        $this->actingAs($user)
            ->patch(route('channels.update', [$user->currentTeam, $channel]), [
                'name' => 'Wall',
                'type' => ChannelType::Advanced->value,
                'zones' => [
                    ['name' => 'Main', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'z_index' => 1],
                ],
            ]);

        $this->actingAs($user)
            ->post(route('channels.duplicate', [$user->currentTeam, $channel]))
            ->assertRedirect();

        $copy = Channel::query()->where('name', 'Wall copy')->firstOrFail();
        $this->assertSame($user->currentTeam->id, $copy->team_id);
        $this->assertSame(ChannelStatus::Draft, $copy->status);
        $this->assertSame(1, $copy->zones()->count());
    }

    public function test_screens_can_be_assigned_to_a_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);
        $screen = Screen::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('channels.update', [$user->currentTeam, $channel]), [
                'name' => $channel->name,
                'screen_ids' => [$screen->id],
            ])
            ->assertRedirect();

        $this->assertSame($channel->id, $screen->fresh()->current_channel_id);
    }
}
