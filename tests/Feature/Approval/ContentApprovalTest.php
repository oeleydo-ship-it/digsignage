<?php

namespace Tests\Feature\Approval;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\ContentApprovalAction;
use App\Enums\DesignStatus;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Enums\TeamRole;
use App\Enums\TemplateStatus;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_designers_submit_and_content_managers_cannot_approve_their_own_work(): void
    {
        Queue::fake();

        [$team, $managerA, $managerB] = $this->contentManagers();
        $playlist = Playlist::factory()->create([
            'team_id' => $team->id,
            'name' => 'Lobby Loop',
        ]);

        $this->actingAs($managerA)
            ->withoutVite()
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.submit', [$team, 'playlist', $playlist->id]), [
                'comment' => 'Ready for review',
            ])
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->assertSame(PlaylistStatus::PendingApproval, $playlist->fresh()->status);

        $this->actingAs($managerA)
            ->patch(route('playlists.update', [$team, $playlist]), [
                'name' => 'Should stay locked',
            ])
            ->assertSessionHasErrors('status');

        $this->actingAs($managerA)
            ->post(route('approvals.approve', [$team, 'playlist', $playlist->id]))
            ->assertSessionHasErrors('status');

        $this->actingAs($managerB)
            ->get(route('approvals.index', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('approvals/index')
                ->has('pending', 1)
                ->where('pending.0.title', 'Lobby Loop'));

        $this->actingAs($managerB)
            ->from(route('approvals.index', $team))
            ->post(route('approvals.approve', [$team, 'playlist', $playlist->id]), [
                'comment' => 'Looks good',
            ])
            ->assertRedirect(route('approvals.index', $team));

        $this->assertSame(PlaylistStatus::Approved, $playlist->fresh()->status);
        $this->assertDatabaseHas('content_approval_events', [
            'approvable_type' => 'playlist',
            'approvable_id' => $playlist->id,
            'action' => ContentApprovalAction::Approved->value,
            'user_id' => $managerB->id,
        ]);
    }

    public function test_owners_may_approve_their_own_submission_and_publishers_release_it(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $publisher = User::factory()->create();
        $team->members()->attach($publisher, ['role' => TeamRole::Publisher->value]);
        $publisher->switchTeam($team);

        $playlist = Playlist::factory()->create([
            'team_id' => $team->id,
            'name' => 'Owner Deck',
        ]);

        $this->actingAs($owner)
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.submit', [$team, 'playlist', $playlist->id]))
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->actingAs($owner)
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.approve', [$team, 'playlist', $playlist->id]))
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->actingAs($publisher)
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.publish', [$team, 'playlist', $playlist->id]))
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->assertSame(PlaylistStatus::Published, $playlist->fresh()->status);
        $this->assertNotNull($playlist->fresh()->published_at);
    }

    public function test_owners_can_publish_a_draft_playlist_from_the_editor(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $playlist = Playlist::factory()->create([
            'team_id' => $team->id,
            'name' => 'Gwap',
            'status' => PlaylistStatus::Draft,
        ]);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('playlists.edit', [$team, $playlist]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('playlists/edit')
                ->where('approval.can_publish', true)
                ->missing('statuses'));

        $this->actingAs($owner)
            ->from(route('dashboard', $team))
            ->post(route('approvals.publish', [$team, 'playlist', $playlist->id]))
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->assertSame(PlaylistStatus::Published, $playlist->fresh()->status);
        $this->assertNotNull($playlist->fresh()->published_at);
    }

    public function test_content_managers_cannot_publish_and_reject_requires_a_comment(): void
    {
        Queue::fake();

        [$team, $managerA, $managerB] = $this->contentManagers();
        $playlist = Playlist::factory()->create(['team_id' => $team->id]);

        $this->actingAs($managerA)
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.submit', [$team, 'playlist', $playlist->id]))
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->actingAs($managerB)
            ->post(route('approvals.reject', [$team, 'playlist', $playlist->id]))
            ->assertSessionHasErrors('comment');

        $this->actingAs($managerB)
            ->from(route('playlists.edit', [$team, $playlist]))
            ->post(route('approvals.reject', [$team, 'playlist', $playlist->id]), [
                'comment' => 'Missing legal footer',
            ])
            ->assertRedirect(route('playlists.edit', [$team, $playlist]));

        $this->assertSame(PlaylistStatus::Rejected, $playlist->fresh()->status);

        $this->actingAs($managerA)
            ->post(route('approvals.publish', [$team, 'playlist', $playlist->id]))
            ->assertForbidden();
    }

    public function test_members_cannot_submit_and_foreign_teams_cannot_be_approved(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $playlist = Playlist::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('approvals.submit', [$team, 'playlist', $playlist->id]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('approvals.approve', [$team, 'playlist', $playlist->id]))
            ->assertForbidden();
    }

    public function test_editing_approved_content_returns_it_to_draft(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
            'status' => PlaylistStatus::Approved,
            'name' => 'Approved Loop',
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => 'Approved Loop',
                'items' => [
                    [
                        'type' => PlaylistItemType::WebPage->value,
                        'title' => 'Promo',
                        'duration_seconds' => 12,
                        'url' => 'https://example.com/promo',
                    ],
                ],
            ])
            ->assertRedirect(route('playlists.edit', [$user->currentTeam, $playlist]));

        $this->assertSame(PlaylistStatus::Draft, $playlist->fresh()->status);
    }

    public function test_live_channels_cannot_publish_without_a_stream_url(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $team = $user->currentTeam;
        $channel = Channel::factory()->create([
            'team_id' => $team->id,
            'type' => ChannelType::Live,
            'live_url' => null,
            'status' => ChannelStatus::Draft,
        ]);

        $this->actingAs($user)
            ->from(route('channels.edit', [$team, $channel]))
            ->post(route('approvals.submit', [$team, 'channel', $channel->id]))
            ->assertRedirect(route('channels.edit', [$team, $channel]));

        $this->actingAs($user)
            ->from(route('channels.edit', [$team, $channel]))
            ->post(route('approvals.approve', [$team, 'channel', $channel->id]))
            ->assertRedirect(route('channels.edit', [$team, $channel]));

        $this->actingAs($user)
            ->post(route('approvals.publish', [$team, 'channel', $channel->id]))
            ->assertSessionHasErrors('live_url');

        $this->assertSame(ChannelStatus::Approved, $channel->fresh()->status);
    }

    public function test_owners_can_publish_a_draft_channel_from_the_editor(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $playlist = Playlist::factory()->create([
            'team_id' => $team->id,
            'name' => 'TEST',
        ]);
        $channel = Channel::factory()->create([
            'team_id' => $team->id,
            'name' => 'TESTSTT',
            'type' => ChannelType::Playlist,
            'playlist_id' => $playlist->id,
            'status' => ChannelStatus::Draft,
        ]);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('channels.edit', [$team, $channel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('channels/edit')
                ->where('approval.can_publish', true)
                ->missing('statuses'));

        $this->actingAs($owner)
            ->from(route('channels.index', $team))
            ->post(route('approvals.publish', [$team, 'channel', $channel->id]))
            ->assertRedirect(route('channels.index', $team));

        $this->assertSame(ChannelStatus::Published, $channel->fresh()->status);
        $this->assertNotNull($channel->fresh()->published_at);
    }

    public function test_owners_can_publish_a_draft_design(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $design = Design::factory()->create([
            'team_id' => $team->id,
            'name' => 'dEIGNER',
            'status' => DesignStatus::Draft,
        ]);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('designs.edit', [$team, $design]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('designs/edit')
                ->where('approval.can_publish', true)
                ->where('design.status', 'draft'));

        $this->actingAs($owner)
            ->from(route('designs.edit', [$team, $design]))
            ->post(route('approvals.publish', [$team, 'design', $design->id]))
            ->assertRedirect(route('designs.edit', [$team, $design]));

        $this->assertSame(DesignStatus::Published, $design->fresh()->status);
        $this->assertNotNull($design->fresh()->published_at);
    }

    public function test_owners_can_publish_a_draft_template_and_stay_on_the_editor(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $template = Template::factory()->create([
            'team_id' => $team->id,
            'name' => 'Lobby Board',
            'status' => TemplateStatus::Draft,
        ]);

        $this->actingAs($owner)
            ->from(route('templates.edit', [$team, $template]))
            ->post(route('approvals.submit', [$team, 'template', $template->id]))
            ->assertRedirect(route('templates.edit', [$team, $template]));

        $this->actingAs($owner)
            ->from(route('templates.edit', [$team, $template]))
            ->post(route('approvals.approve', [$team, 'template', $template->id]))
            ->assertRedirect(route('templates.edit', [$team, $template]));

        $this->actingAs($owner)
            ->from(route('templates.edit', [$team, $template]))
            ->post(route('approvals.publish', [$team, 'template', $template->id]))
            ->assertRedirect(route('templates.edit', [$team, $template]));

        $this->assertSame(TemplateStatus::Published, $template->fresh()->status);
        $this->assertNotNull($template->fresh()->published_at);
    }

    /**
     * @return array{0: Team, 1: User, 2: User}
     */
    protected function contentManagers(): array
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $managerA = User::factory()->create();
        $managerB = User::factory()->create();
        $team->members()->attach($managerA, ['role' => TeamRole::ContentManager->value]);
        $team->members()->attach($managerB, ['role' => TeamRole::ContentManager->value]);
        $managerA->switchTeam($team);
        $managerB->switchTeam($team);

        return [$team, $managerA, $managerB];
    }
}
