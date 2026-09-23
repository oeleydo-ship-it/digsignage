<?php

namespace Tests\Feature\Performance;

use App\Enums\ChannelType;
use App\Enums\PlaylistItemType;
use App\Models\Channel;
use App\Models\Location;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard rails against query-count regressions on the hottest endpoints.
 *
 * The limits are generous enough to survive unrelated changes, but they
 * catch accidental N+1s and dropped caches.
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_query_count_stays_bounded(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create(['team_id' => $team->id]);
        Screen::factory()->count(12)->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
        ]);

        $first = $this->countQueries(fn () => $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertOk());

        $second = $this->countQueries(fn () => $this->actingAs($user)
            ->get(route('dashboard', $team))
            ->assertOk());

        fwrite(STDERR, "\n[performance] dashboard queries: first={$first} second={$second}\n");
        $this->assertLessThanOrEqual(30, $first);
        // Plan catalog, usage summary, announcements, and inbox badge are
        // cached after the first load.
        $this->assertLessThan($first, $second);
    }

    public function test_screens_index_query_count_stays_bounded(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create(['team_id' => $team->id]);
        Screen::factory()->count(25)->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
        ]);

        $count = $this->countQueries(fn () => $this->actingAs($user)
            ->get(route('screens.index', $team))
            ->assertOk());

        fwrite(STDERR, "\n[performance] screens index queries: {$count}\n");
        // The first request also resolves the team's cached plan feature map
        // so navigation can omit modules that are not entitled.
        $this->assertLessThanOrEqual(31, $count);
    }

    public function test_player_manifest_is_cached_between_polls(): void
    {
        [$screen, $token] = $this->pairedScreenWithChannel();

        $first = $this->countQueries(fn () => $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk());

        $second = $this->countQueries(fn () => $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk());

        fwrite(STDERR, "\n[performance] manifest queries: first={$first} second={$second}\n");

        // The second poll should be served from the manifest cache: only the
        // device-token lookup (and cache version read on database stores)
        // may hit the database.
        $this->assertLessThanOrEqual(2, $second);
        $this->assertGreaterThan($second, $first);
    }

    public function test_manifest_cache_is_invalidated_when_content_changes(): void
    {
        [$screen, $token] = $this->pairedScreenWithChannel();

        $manifest = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('playlist', $manifest['playback']['type']);

        // Renaming the playlist must bust the cached manifest.
        $playlist = Playlist::query()->where('team_id', $screen->team_id)->firstOrFail();
        $playlist->forceFill(['name' => 'Renamed Playlist'])->save();

        $updated = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json();

        $this->assertSame('Renamed Playlist', $updated['playback']['playlist']['name']);
    }

    /**
     * @return array{0: Screen, 1: string}
     */
    protected function pairedScreenWithChannel(): array
    {
        $user = User::factory()->create();
        $plain = 'player-device-token';
        $screen = Screen::factory()->paired()->create([
            'team_id' => $user->currentTeam->id,
            'device_token' => bcrypt($plain),
            'device_token_hash' => DeviceToken::hash($plain),
        ]);

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
            'type' => ChannelType::Playlist,
        ]);
        $screen->forceFill(['current_channel_id' => $channel->id])->save();

        return [$screen, $plain];
    }

    protected function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return $count;
    }
}
