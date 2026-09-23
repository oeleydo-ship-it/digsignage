<?php

namespace Tests\Feature\Signage;

use App\Enums\ScreenOrientation;
use App\Enums\ScreenStatus;
use App\Enums\TeamRole;
use App\Models\Location;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->from(route('screens.index', $team))
            ->post(route('screens.store', $team), [
                'name' => 'Lobby Display',
                'location_id' => $location->id,
                'orientation' => ScreenOrientation::Landscape->value,
                'resolution_width' => 1920,
                'resolution_height' => 1080,
            ])
            ->assertRedirect(route('screens.index', $team));

        $this->assertDatabaseHas('screens', [
            'team_id' => $team->id,
            'name' => 'Lobby Display',
            'location_id' => $location->id,
            'status' => ScreenStatus::Offline->value,
        ]);
    }

    public function test_screens_cannot_be_assigned_to_another_teams_location(): void
    {
        $user = User::factory()->create();
        $foreignLocation = Location::factory()->create();

        $this->actingAs($user)
            ->post(route('screens.store', $user->currentTeam), [
                'name' => 'Lobby Display',
                'location_id' => $foreignLocation->id,
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertSessionHasErrors('location_id');
    }

    public function test_user_cannot_view_or_update_another_teams_screen(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $screenB = Screen::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Private Screen',
        ]);

        $this->actingAs($userA)
            ->patch(route('screens.update', [$userA->currentTeam, $screenB]), [
                'name' => 'Stolen',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('screens', [
            'id' => $screenB->id,
            'name' => 'Private Screen',
        ]);
    }

    public function test_members_cannot_create_screens(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('screens.store', $team), [
                'name' => 'No Access',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertForbidden();
    }

    public function test_admins_can_bulk_disable_screens(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $admin->switchTeam($team);

        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $this->actingAs($admin)
            ->post(route('screens.bulk', $team), [
                'action' => 'disable',
                'screen_ids' => [$screen->id],
            ])
            ->assertRedirect();

        $this->assertEquals(ScreenStatus::Disabled, $screen->fresh()->status);
    }

    public function test_pairing_a_screen_stays_on_the_screens_page(): void
    {
        $code = $this->postJson('/api/player/v1/registrations')->json('code');
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $index = route('screens.index', $team);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->post(route('screens.pair', $team), [
                'code' => $code,
                'name' => 'Lobby Display',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect($index);

        $this->assertDatabaseHas('screens', [
            'team_id' => $team->id,
            'name' => 'Lobby Display',
        ]);
    }

    public function test_invalid_pair_does_not_dump_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $index = route('screens.index', $team);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->post(route('screens.pair', $team), [
                'code' => 'ABCD-1234',
                'name' => '',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect($index)
            ->assertSessionHasErrors('name');
    }

    public function test_updating_and_deleting_a_screen_stays_on_the_screens_page(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id, 'name' => 'Old Name']);
        $index = route('screens.index', $team);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->patch(route('screens.update', [$team, $screen]), [
                'name' => 'New Name',
                'orientation' => ScreenOrientation::Landscape->value,
            ])
            ->assertRedirect($index);

        $this->assertDatabaseHas('screens', [
            'id' => $screen->id,
            'name' => 'New Name',
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->withHeader('X-Stay-On-Page', $index)
            ->delete(route('screens.destroy', [$team, $screen]))
            ->assertRedirect($index);

        $this->assertSoftDeleted($screen);
    }
}
