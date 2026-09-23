<?php

namespace Tests\Feature\Queue;

use App\Enums\ChannelStatus;
use App\Enums\DesignStatus;
use App\Enums\PlaylistStatus;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\QueueService;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceToken;
use App\Support\QueueBoardPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_page_exposes_presets_and_queue_boards(): void
    {
        $user = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Lobby Queue',
            'document' => QueueBoardPresets::document('classic'),
        ]);

        $this->actingAs($user)
            ->get(route('queue.displays', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/displays')
                ->has('presets', 3)
                ->where('queueBoards.0.id', $design->id)
                ->where('displayPermissions.canCreateBoard', true)
                ->where('displayPermissions.canDeploy', true));
    }

    public function test_owner_can_create_an_editable_media_split_queue_board(): void
    {
        $user = User::factory()->create();
        $service = QueueService::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Registration',
        ]);

        $response = $this->actingAs($user)
            ->post(route('queue.displays.boards.store', $user->currentTeam), [
                'preset' => 'media_split',
                'name' => 'Registration Board',
                'service_id' => $service->id,
            ]);

        $design = Design::query()->where('name', 'Registration Board')->firstOrFail();
        $response->assertRedirect(route('designs.edit', [$user->currentTeam, $design]));

        $elements = collect($design->document['elements']);
        $this->assertSame(DesignStatus::Draft, $design->status);
        $this->assertTrue($elements->contains('type', 'queue_now_serving'));
        $this->assertTrue($elements->contains('type', 'queue_ticker'));
        $this->assertTrue($elements->contains('type', 'video'));
        $this->assertSame(
            (string) $service->id,
            $elements->firstWhere('type', 'queue_now_serving')['props']['service_id'],
        );
        $this->assertTrue($elements->firstWhere('type', 'queue_now_serving')['props']['sound']);
    }

    public function test_published_queue_board_can_be_deployed_through_playlist_and_channel(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $design = Design::factory()->create([
            'team_id' => $team->id,
            'name' => 'Main Queue Board',
            'status' => DesignStatus::Published,
            'published_at' => now(),
            'document' => QueueBoardPresets::document('lobby'),
        ]);
        $token = 'queue-board-player-token';
        $player = Screen::factory()->create([
            'team_id' => $team->id,
            'device_uuid' => fake()->uuid(),
            'device_token' => bcrypt($token),
            'device_token_hash' => DeviceToken::hash($token),
        ]);
        $screens = collect([
            $player,
            Screen::factory()->create(['team_id' => $team->id]),
        ]);

        $this->actingAs($user)
            ->post(route('queue.displays.deploy', $team), [
                'design_id' => $design->id,
                'screen_ids' => $screens->pluck('id')->all(),
            ])
            ->assertRedirect(route('queue.displays', $team));

        $playlist = Playlist::query()->where('team_id', $team->id)->firstOrFail();
        $channel = Channel::query()->where('team_id', $team->id)->firstOrFail();

        $this->assertSame(PlaylistStatus::Published, $playlist->status);
        $this->assertSame($design->id, $playlist->items()->firstOrFail()->design_id);
        $this->assertSame(ChannelStatus::Published, $channel->status);
        $this->assertSame($playlist->id, $channel->playlist_id);
        $this->assertSame(
            [$channel->id, $channel->id],
            $screens->map(fn (Screen $screen) => $screen->fresh()->current_channel_id)->all(),
        );

        $item = $this->withToken($token)
            ->getJson('/api/player/v1/manifest')
            ->assertOk()
            ->json('playback.playlist.items.0');

        $this->assertSame('design', $item['type']);
        $this->assertSame(
            'queue_now_serving',
            collect($item['document']['elements'])->firstWhere('type', 'queue_now_serving')['widget']['key'],
        );
        $this->assertTrue(
            collect($item['document']['elements'])->firstWhere('type', 'queue_now_serving')['widget']['settings']['sound'],
        );
    }

    public function test_non_queue_design_and_cross_team_screens_cannot_be_deployed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $user->currentTeam->id,
            'status' => DesignStatus::Published,
            'published_at' => now(),
        ]);
        $foreignScreen = Screen::factory()->create(['team_id' => $other->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('queue.displays.deploy', $user->currentTeam), [
                'design_id' => $design->id,
                'screen_ids' => [$foreignScreen->id],
            ])
            ->assertSessionHasErrors('screen_ids.0');

        $ownScreen = Screen::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('queue.displays.deploy', $user->currentTeam), [
                'design_id' => $design->id,
                'screen_ids' => [$ownScreen->id],
            ])
            ->assertSessionHasErrors('design_id');

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_read_only_queue_member_cannot_create_or_deploy_boards(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        $design = Design::factory()->create([
            'team_id' => $team->id,
            'status' => DesignStatus::Published,
            'published_at' => now(),
            'document' => QueueBoardPresets::document('classic'),
        ]);
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('queue.displays.boards.store', $team), ['preset' => 'classic'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('queue.displays.deploy', $team), [
                'design_id' => $design->id,
                'screen_ids' => [$screen->id],
            ])
            ->assertForbidden();
    }
}
