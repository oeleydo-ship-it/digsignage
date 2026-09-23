<?php

namespace Tests\Feature\Design;

use App\Enums\DesignElementType;
use App\Enums\DesignStatus;
use App\Enums\PlaylistItemType;
use App\Enums\TeamRole;
use App\Models\Design;
use App\Models\Playlist;
use App\Models\Team;
use App\Models\User;
use App\Support\DesignDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_and_edit_designs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('designs.store', $user->currentTeam), [
                'name' => 'Lobby Welcome',
                'width' => 1920,
                'height' => 1080,
            ])
            ->assertRedirect();

        $design = Design::query()->firstOrFail();

        $this->assertSame(DesignStatus::Draft, $design->status);
        $this->assertSame(1920, $design->width);

        $document = DesignDocument::blank();
        $document['elements'][] = [
            'id' => 'el-1',
            'type' => DesignElementType::Text->value,
            'name' => 'Headline',
            'x' => 40,
            'y' => 40,
            'width' => 400,
            'height' => 80,
            'props' => ['text' => 'Hello'],
        ];

        $this->actingAs($user)
            ->patch(route('designs.update', [$user->currentTeam, $design]), [
                'name' => 'Lobby Welcome',
                'document' => $document,
            ])
            ->assertRedirect();

        $this->assertSame(2, $design->fresh()->version);
        $this->assertDatabaseCount('design_revisions', 2);
    }

    public function test_user_cannot_edit_another_teams_design(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret',
        ]);

        $this->actingAs($userA)
            ->patch(route('designs.update', [$userA->currentTeam, $design]), [
                'name' => 'Stolen',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('designs', [
            'id' => $design->id,
            'name' => 'Secret',
        ]);
    }

    public function test_members_cannot_create_designs(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('designs.store', $team), [
                'name' => 'No Access',
            ])
            ->assertForbidden();
    }

    public function test_elements_are_kept_inside_the_canvas_when_saved(): void
    {
        $document = DesignDocument::blank(800, 600);
        $document['elements'][] = [
            'id' => 'oversized-image',
            'type' => DesignElementType::Image->value,
            'name' => 'Hero image',
            'x' => -100,
            'y' => 590,
            'width' => 1000,
            'height' => 300,
            'props' => [
                'media_id' => 42,
                'objectFit' => 'cover',
            ],
        ];

        $normalized = DesignDocument::normalize($document);
        $element = $normalized['elements'][0];

        $this->assertEquals(0, $element['x']);
        $this->assertEquals(300, $element['y']);
        $this->assertEquals(800, $element['width']);
        $this->assertEquals(300, $element['height']);
        $this->assertSame(42, $element['props']['media_id']);
    }

    public function test_draft_designs_cannot_be_added_to_playlists(): void
    {
        $user = User::factory()->create();
        $design = Design::factory()->create([
            'team_id' => $user->currentTeam->id,
            'status' => DesignStatus::Draft,
        ]);
        $playlist = Playlist::factory()->create([
            'team_id' => $user->currentTeam->id,
        ]);

        $this->actingAs($user)
            ->patch(route('playlists.update', [$user->currentTeam, $playlist]), [
                'name' => $playlist->name,
                'items' => [
                    [
                        'type' => PlaylistItemType::Design->value,
                        'title' => 'Draft canvas',
                        'duration_seconds' => 10,
                        'design_id' => $design->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.0.design_id');
    }
}
