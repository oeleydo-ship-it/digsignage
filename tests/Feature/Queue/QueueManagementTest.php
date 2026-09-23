<?php

namespace Tests\Feature\Queue;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\QueueSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    protected function queuePages(): array
    {
        return [
            'queue.overview' => 'queue/overview',
            'queue.live' => 'queue/live',
            'queue.services' => 'queue/services',
            'queue.counters' => 'queue/counters',
            'queue.kiosks' => 'queue/kiosks',
            'queue.tickets' => 'queue/tickets',
            'queue.appointments' => 'queue/appointments',
            'queue.displays' => 'queue/displays',
            'queue.reports' => 'queue/reports',
            'queue.settings' => 'queue/settings',
        ];
    }

    /** @return array<string, string> */
    protected function memberQueuePages(): array
    {
        return array_diff_key($this->queuePages(), ['queue.reports' => true]);
    }

    public function test_owners_can_visit_queue_pages(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        foreach ($this->queuePages() as $route => $component) {
            $this->actingAs($user)
                ->get(route($route, $team))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_members_can_visit_queue_pages(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->assertTrue($member->hasTeamPermission($team, TeamPermission::ViewQueue));
        $this->assertFalse($member->hasTeamPermission($team, TeamPermission::ManageQueue));

        foreach ($this->memberQueuePages() as $route => $component) {
            $this->actingAs($member)
                ->get(route($route, $team))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($member)
            ->get(route('queue.reports', $team))
            ->assertForbidden();
    }

    public function test_guests_cannot_visit_queue_pages(): void
    {
        $team = Team::factory()->create();

        foreach (array_keys($this->queuePages()) as $route) {
            $this->get(route($route, $team))
                ->assertRedirect(route('login'));
        }
    }

    public function test_user_cannot_open_another_teams_queue_routes(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        foreach (array_keys($this->queuePages()) as $route) {
            $this->actingAs($userA)
                ->get(route($route, $userB->currentTeam))
                ->assertForbidden();
        }
    }

    public function test_admins_can_manage_queue(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $admin->switchTeam($team);

        $this->assertTrue($admin->hasTeamPermission($team, TeamPermission::ViewQueue));
        $this->assertTrue($admin->hasTeamPermission($team, TeamPermission::ManageQueue));
    }

    public function test_overview_creates_a_queue_settings_row_for_the_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->assertDatabaseMissing('queue_settings', [
            'team_id' => $team->id,
        ]);

        $this->actingAs($user)
            ->get(route('queue.overview', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/overview')
                ->where('operations.stats.waiting', 0)
                ->where('operations.stats.serving', 0)
                ->where('operations.stats.completed_today', 0)
                ->where('operations.stats.no_shows_today', 0)
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', true));

        $this->assertDatabaseHas('queue_settings', [
            'team_id' => $team->id,
        ]);

        $this->assertSame(1, QueueSetting::query()->where('team_id', $team->id)->count());
    }

    public function test_visiting_another_team_does_not_create_their_queue_settings(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $teamB = $userB->currentTeam;

        $this->actingAs($userA)
            ->get(route('queue.overview', $teamB))
            ->assertForbidden();

        $this->assertDatabaseMissing('queue_settings', [
            'team_id' => $teamB->id,
        ]);
    }
}
