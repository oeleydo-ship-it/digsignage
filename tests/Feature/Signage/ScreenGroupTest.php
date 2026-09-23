<?php

namespace Tests\Feature\Signage;

use App\Enums\TeamRole;
use App\Models\Screen;
use App\Models\ScreenGroup;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreenGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_create_groups_and_assign_screens(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $screen = Screen::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->post(route('screen-groups.store', $team), [
                'name' => 'Reception Screens',
            ])
            ->assertRedirect();

        $group = ScreenGroup::query()->first();

        $this->actingAs($user)
            ->put(route('screen-groups.screens.sync', [$team, $group]), [
                'screen_ids' => [$screen->id],
            ])
            ->assertRedirect();

        $this->assertTrue($group->screens()->whereKey($screen->id)->exists());
    }

    public function test_groups_cannot_attach_another_teams_screen(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $group = ScreenGroup::factory()->create(['team_id' => $userA->currentTeam->id]);
        $foreignScreen = Screen::factory()->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA)
            ->put(route('screen-groups.screens.sync', [$userA->currentTeam, $group]), [
                'screen_ids' => [$foreignScreen->id],
            ])
            ->assertSessionHasErrors('screen_ids.0');
    }

    public function test_user_cannot_update_another_teams_group(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $groupB = ScreenGroup::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Private Group',
        ]);

        $this->actingAs($userA)
            ->patch(route('screen-groups.update', [$userA->currentTeam, $groupB]), [
                'name' => 'Hijacked Group',
            ])
            ->assertForbidden();
    }

    public function test_members_can_view_groups_but_not_create_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        ScreenGroup::factory()->create(['team_id' => $team->id, 'name' => 'Visible']);

        $this->actingAs($member)
            ->get(route('screen-groups.index', $team))
            ->assertOk();

        $this->actingAs($member)
            ->post(route('screen-groups.store', $team), [
                'name' => 'Hidden',
            ])
            ->assertForbidden();
    }
}
