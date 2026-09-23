<?php

namespace Tests\Feature\Signage;

use App\Enums\LocationType;
use App\Enums\TeamRole;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_and_list_locations(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->post(route('locations.store', $team), [
                'name' => 'Dubai',
                'type' => LocationType::City->value,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('locations.index', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('signage/locations/index')
                ->has('locations', 1)
                ->where('locations.0.name', 'Dubai'));
    }

    public function test_location_parent_must_belong_to_the_same_team(): void
    {
        $user = User::factory()->create();
        $foreign = Location::factory()->create();

        $this->actingAs($user)
            ->post(route('locations.store', $user->currentTeam), [
                'name' => 'Lobby',
                'type' => LocationType::Area->value,
                'parent_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_locations_cannot_be_moved_under_descendants(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $country = Location::factory()->create([
            'team_id' => $team->id,
            'name' => 'UAE',
            'type' => LocationType::Country,
        ]);
        $city = Location::factory()->create([
            'team_id' => $team->id,
            'parent_id' => $country->id,
            'name' => 'Dubai',
            'type' => LocationType::City,
        ]);

        $this->actingAs($user)
            ->patch(route('locations.update', [$team, $country]), [
                'name' => 'UAE',
                'type' => LocationType::Country->value,
                'parent_id' => $city->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_members_cannot_create_locations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)
            ->post(route('locations.store', $team), [
                'name' => 'Restricted',
                'type' => LocationType::Area->value,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_update_another_teams_location(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $locationB = Location::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Secret',
        ]);

        $this->actingAs($userA)
            ->patch(route('locations.update', [$userA->currentTeam, $locationB]), [
                'name' => 'Hijacked',
                'type' => LocationType::City->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $locationB->id,
            'name' => 'Secret',
        ]);
    }
}
