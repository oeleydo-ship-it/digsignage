<?php

namespace Tests\Feature\Playlist;

use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Enums\TeamRole;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistRevision;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_playlists_with_calculated_duration(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'duration' => 30,
        ]);

        $this->actingAs($user)
            ->post(route('playlists.store', $user->currentTeam), [
                'name' => 'Morning Playlist',
                'loop' => true,
                'items' => [
                    [
                        'type' => PlaylistItemType::Media->value,
                        'title' => 'Welcome Video',
                        'duration_seconds' => 30,
                        'media_id' => $media->id,
                        'enabled' => true,
                    ],
                    [
                        'type' => PlaylistItemType::WebPage->value,
                        'title' => 'Company News',
                        'duration_seconds' => 20,
                        'url' => 'https://example.com/news',
                        'enabled' => true,
                    ],
                    [
                        'type' => PlaylistItemType::Widget->value,
                        'title' => 'Weather',
                        'duration_seconds' => 15,
                        'widget_key' => 'weather',
                        'enabled' => false,
                    ],
                ],
            ])
            ->assertRedirect();

        $playlist = Playlist::query()->firstOrFail();
        $this->assertSame($user->currentTeam->id, $playlist->team_id);
        $this->assertSame(50, $playlist->duration_seconds);
        $this->assertTrue($playlist->loop);
        $this->assertSame(3, $playlist->items()->count());
        $this->assertSame(1, $playlist->revisions()->count());
    }

    public function test_user_cannot_update_another_teams_playlist(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret sequence',
        ]);

        $this->actingAs($userA)
            ->patch(route('playlists.update', [$userA->currentTeam, $playlist]), [
                'name' => 'Stolen',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('playlists', [
            'id' => $playlist->id,
            'name' => 'Secret sequence',
        ]);
    }

    public function test_user_cannot_attach_another_teams_media(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $userB->currentTeam->id,
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $userA->currentTeam->id,
        ]);

        $this->actingAs($userA)
            ->patch(route('playlists.update', [$userA->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Media->value,
                        'title' => 'Stolen clip',
                        'duration_seconds' => 10,
                        'media_id' => $media->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.media_id');
    }

    public function test_members_cannot_create_playlists(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('playlists.store', $team), [
                'name' => 'No Access',
            ])
            ->assertForbidden();
    }

    public function test_playlists_can_be_duplicated_with_items(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Lobby Loop',
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => 'Lobby Loop',
                'items' => [
                    [
                        'type' => PlaylistItemType::WebPage->value,
                        'title' => 'Promo',
                        'duration_seconds' => 20,
                        'url' => 'https://example.com/promo',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('playlists.duplicate', [$user->currentTeam, $playlist]))
            ->assertRedirect();

        $copy = Playlist::query()->where('name', 'Lobby Loop copy')->firstOrFail();
        $this->assertSame($user->currentTeam->id, $copy->team_id);
        $this->assertSame(PlaylistStatus::Draft, $copy->status);
        $this->assertSame(1, $copy->items()->count());
        $this->assertSame(20, $copy->duration_seconds);
    }

    public function test_item_changes_increment_playlist_version(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::LiveStream->value,
                        'title' => 'Lobby cam',
                        'duration_seconds' => 60,
                        'url' => 'https://example.com/live',
                    ],
                ],
            ])
            ->assertRedirect();

        $playlist->refresh();
        $this->assertSame(2, $playlist->version);
        $this->assertTrue($playlist->revisions()->where('version', 2)->exists());
    }

    public function test_revisions_can_be_restored(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Restore me',
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => 'Restore me',
                'items' => [
                    [
                        'type' => PlaylistItemType::WebPage->value,
                        'title' => 'First',
                        'duration_seconds' => 10,
                        'url' => 'https://example.com/first',
                    ],
                ],
            ]);

        $firstRevision = PlaylistRevision::query()->where('playlist_id', $playlist->id)->where('version', 2)->firstOrFail();

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => 'Restore me',
                'items' => [
                    [
                        'type' => PlaylistItemType::WebPage->value,
                        'title' => 'Second',
                        'duration_seconds' => 40,
                        'url' => 'https://example.com/second',
                    ],
                ],
            ]);

        $this->actingAs($user)
            ->post(route('playlists.revisions.restore', [$user->currentTeam, $playlist, $firstRevision]))
            ->assertRedirect();

        $this->assertSame('First', $playlist->fresh()->items()->first()?->title);
    }

    public function test_editor_includes_media_preview_urls(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Lobby photo',
            'thumbnail_path' => 'thumbs/demo.jpg',
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $user->currentTeam->id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Media,
            'title' => $media->name,
            'media_id' => $media->id,
            'url' => null,
        ]);

        $preview = route('media.thumbnail', [
            'current_team' => $user->currentTeam->slug,
            'media' => $media,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('playlists.edit', [$user->currentTeam, $playlist]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('playlists/edit')
                ->where('catalog.media.0.preview_url', $preview)
                ->where('playlist.items.0.preview_url', $preview)
                ->missing('catalog.templates')
                ->has('catalog.media')
                ->has('catalog.designs')
                ->where('itemTypes', fn ($types) => collect($types)->pluck('value')->doesntContain('template')));
    }

    public function test_templates_cannot_be_attached_to_playlists(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->published()->create([
            'team_id' => $user->currentTeam->id,
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Template->value,
                        'title' => $template->name,
                        'duration_seconds' => 20,
                        'template_id' => $template->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.type');
    }

    public function test_existing_template_playlist_items_can_be_kept(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->published()->create([
            'team_id' => $user->currentTeam->id,
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);
        PlaylistItem::factory()->create([
            'team_id' => $user->currentTeam->id,
            'playlist_id' => $playlist->id,
            'type' => PlaylistItemType::Template,
            'title' => $template->name,
            'template_id' => $template->id,
            'url' => null,
            'duration_seconds' => 20,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Template->value,
                        'title' => $template->name,
                        'duration_seconds' => 25,
                        'template_id' => $template->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(25, $playlist->fresh()->items()->first()?->duration_seconds);
        $this->assertSame($template->id, $playlist->fresh()->items()->first()?->template_id);
    }
}
