<?php

namespace Tests\Feature\Player;

use App\Enums\ChannelType;
use App\Enums\DesignStatus;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Enums\ScreenStatus;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_play_page_is_public(): void
    {
        $this->withoutVite()
            ->get(route('player.play'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('player/play'));
    }

    public function test_manifest_requires_a_device_token(): void
    {
        $this->getJson('/api/player/v1/manifest')->assertUnauthorized();
    }

    public function test_player_receives_a_manifest_with_checksummed_assets(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('team/welcome.bin', 'welcome-bytes');
        $checksum = hash('sha256', 'welcome-bytes');

        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        $media = Media::factory()->create([
            'team_id' => $screen->team_id,
            'storage_path' => 'team/welcome.bin',
            'checksum' => $checksum,
            'file_size' => 13,
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Media,
            'title' => 'Welcome Video',
            'duration_seconds' => 30,
            'media_id' => $media->id,
            'url' => null,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
            'name' => 'Reception',
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('playlist', $manifest['playback']['type']);
        $this->assertSame('Reception', $manifest['playback']['channel']['name']);
        $this->assertSame('Welcome Video', $manifest['playback']['playlist']['items'][0]['title']);
        $this->assertSame($checksum, $manifest['assets'][0]['checksum']);
        $this->assertIsInt($manifest['version']);
    }

    public function test_player_cannot_download_another_teams_media(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('secret.bin', 'secret');

        [$screen, $token] = $this->pairedScreen();
        $foreign = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $foreign->currentTeam->id,
            'storage_path' => 'secret.bin',
            'checksum' => hash('sha256', 'secret'),
        ]);

        $this->withToken($token)
            ->get('/api/player/v1/assets/media/'.$media->id)
            ->assertNotFound();
    }

    public function test_player_can_download_manifest_media(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('team/clip.bin', 'clip-bytes');

        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        $media = Media::factory()->create([
            'team_id' => $screen->team_id,
            'storage_path' => 'team/clip.bin',
            'checksum' => hash('sha256', 'clip-bytes'),
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Media,
            'media_id' => $media->id,
            'url' => null,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $this->withToken($token)
            ->get('/api/player/v1/assets/media/'.$media->id)
            ->assertOk();
    }

    public function test_heartbeat_marks_the_screen_online(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', [
                'player_version' => 'web-1.0',
                'current_content' => 'Welcome Video',
            ])
            ->assertOk();

        $screen->refresh();
        $this->assertSame(ScreenStatus::Online, $screen->status);
        $this->assertSame('web-1.0', $screen->app_version);
        $this->assertNotNull($screen->last_seen_at);
        $this->assertSame('Welcome Video', $screen->metadata['player']['current_content']);
    }

    public function test_playback_events_are_recorded_for_the_screen_team(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->postJson('/api/player/v1/playback', [
                'item_key' => 'item:1',
                'title' => 'Welcome Video',
                'duration_ms' => 30000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('player_playback_events', [
            'screen_id' => $screen->id,
            'team_id' => $screen->team_id,
            'title' => 'Welcome Video',
            'content_id' => 'item:1',
            'status' => 'completed',
        ]);
    }

    public function test_foreign_device_token_is_rejected(): void
    {
        $this->pairedScreen();

        $this->withToken('not-a-real-token')
            ->getJson('/api/player/v1/session')
            ->assertUnauthorized();
    }

    public function test_commands_endpoint_is_ready_for_later_phases(): void
    {
        [, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->getJson('/api/player/v1/commands')
            ->assertOk()
            ->assertJson(['commands' => []]);
    }

    public function test_widget_playlist_items_are_not_checksummed_as_media(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Widget,
            'title' => 'Clock',
            'duration_seconds' => 12,
            'url' => null,
            'widget_key' => 'clock',
            'widget_settings' => ['format' => 'HH:mm'],
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('widget', $manifest['playback']['playlist']['items'][0]['type']);
        $this->assertSame('clock', $manifest['playback']['playlist']['items'][0]['widget']['key']);
        $this->assertSame([], $manifest['assets']);
    }

    public function test_design_widgets_are_not_cached_as_checksummed_files(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put('team/photo.bin', 'photo-bytes');
        $checksum = hash('sha256', 'photo-bytes');

        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        $media = Media::factory()->create([
            'team_id' => $screen->team_id,
            'storage_path' => 'team/photo.bin',
            'checksum' => $checksum,
            'file_size' => 11,
        ]);
        $design = Design::factory()->create([
            'team_id' => $screen->team_id,
            'status' => DesignStatus::Published,
            'document' => [
                'width' => 1920,
                'height' => 1080,
                'background' => '#111827',
                'elements' => [
                    [
                        'id' => 'clock-1',
                        'type' => 'clock',
                        'name' => 'Clock',
                        'x' => 0,
                        'y' => 0,
                        'width' => 400,
                        'height' => 120,
                        'rotation' => 0,
                        'opacity' => 1,
                        'zIndex' => 1,
                        'locked' => false,
                        'hidden' => false,
                        'props' => ['format' => 'HH:mm'],
                    ],
                    [
                        'id' => 'photo-1',
                        'type' => 'image',
                        'name' => 'Photo',
                        'x' => 0,
                        'y' => 200,
                        'width' => 800,
                        'height' => 450,
                        'rotation' => 0,
                        'opacity' => 1,
                        'zIndex' => 2,
                        'locked' => false,
                        'hidden' => false,
                        'props' => ['media_id' => $media->id],
                    ],
                ],
            ],
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Design,
            'design_id' => $design->id,
            'title' => 'Board',
            'duration_seconds' => 12,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $assets = collect($manifest['assets'])->keyBy('key');

        $this->assertNull($assets['design:'.$design->id]['checksum']);
        $this->assertNull($assets['design:'.$design->id]['url']);
        $this->assertSame($checksum, $assets['media:'.$media->id]['checksum']);
        $this->assertSame('/api/player/v1/assets/media/'.$media->id, $assets['media:'.$media->id]['url']);
        $this->assertSame('clock', $manifest['playback']['playlist']['items'][0]['document']['elements'][0]['widget']['key']);
    }

    public function test_manifest_includes_external_video_urls(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        $media = Media::factory()->videoUrl('https://cdn.example.com/loop.mp4')->create([
            'team_id' => $screen->team_id,
            'name' => 'Loop',
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Media,
            'title' => 'Loop',
            'duration_seconds' => 20,
            'media_id' => $media->id,
            'url' => null,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('video', $manifest['assets'][0]['type']);
        $this->assertSame('https://cdn.example.com/loop.mp4', $manifest['assets'][0]['url']);
    }

    public function test_manifest_is_empty_when_screen_has_no_channel(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('empty', $manifest['playback']['type']);
        $this->assertNull($manifest['playback']['channel']);
        $this->assertSame($screen->name, $manifest['fallback']['screen_name']);
        $this->assertSame('Waiting for content', $manifest['fallback']['message']);
        $this->assertSame('#0b0b0f', $manifest['fallback']['background']);
        $this->assertSame('DigSignage', $manifest['fallback']['brand']);
    }

    public function test_manifest_is_empty_when_playlist_has_no_enabled_items(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->published()->create(['team_id' => $screen->team_id]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'enabled' => false,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('empty', $manifest['playback']['type']);
        $this->assertSame([], $manifest['playback']['playlist']['items']);
        $this->assertSame('Waiting for content', $manifest['fallback']['message']);
    }

    public function test_unpublished_playlist_is_treated_as_empty(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $playlist = Playlist::factory()->create([
            'team_id' => $screen->team_id,
            'status' => PlaylistStatus::Draft,
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'enabled' => true,
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $screen->team_id,
            'playlist_id' => $playlist->id,
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('empty', $manifest['playback']['type']);
        $this->assertSame([], $manifest['playback']['playlist']['items']);
    }

    public function test_live_channel_without_url_is_empty(): void
    {
        [$screen, $token] = $this->pairedScreen();
        $channel = Channel::factory()->live()->create([
            'team_id' => $screen->team_id,
            'live_url' => null,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('empty', $manifest['playback']['type']);
        $this->assertSame('Waiting for content', $manifest['fallback']['message']);
    }

    public function test_deleted_screens_device_token_is_rejected(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $this->withToken($token)
            ->getJson('/api/player/v1/session')
            ->assertOk();

        $screen->delete();

        $this->withToken($token)
            ->getJson('/api/player/v1/session')
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertUnauthorized();

        $this->withToken($token)
            ->postJson('/api/player/v1/heartbeat', ['player_version' => 'web-1.0'])
            ->assertUnauthorized();
    }

    public function test_unpaired_screen_credentials_are_rejected(): void
    {
        [$screen, $token] = $this->pairedScreen();

        $screen->forceFill([
            'device_token' => null,
            'device_token_hash' => null,
        ])->save();

        $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertUnauthorized();
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
