<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\PlaylistStatus;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_for_the_current_organization(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'action' => AuditAction::UserLoggedIn->value,
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);
    }

    public function test_invites_role_changes_and_playlist_publish_are_audited(): void
    {
        Notification::fake();
        Queue::fake();

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($owner)
            ->post(route('teams.invitations.store', $team), [
                'email' => 'new-editor@example.com',
                'role' => TeamRole::ContentManager->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'action' => AuditAction::UserInvited->value,
        ]);

        $this->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), [
                'role' => TeamRole::Publisher->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'action' => AuditAction::RoleChanged->value,
            'resource_id' => $member->id,
        ]);

        $playlist = Playlist::factory()->create([
            'team_id' => $team->id,
            'status' => PlaylistStatus::Approved,
            'name' => 'Lobby Loop',
        ]);

        $this->actingAs($owner)
            ->post(route('approvals.publish', [$team, 'playlist', $playlist->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'team_id' => $team->id,
            'action' => AuditAction::PlaylistPublished->value,
            'resource_type' => 'playlist',
            'resource_id' => $playlist->id,
        ]);
    }

    public function test_owners_can_search_and_export_logs_while_members_cannot(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        AuditLog::factory()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'action' => AuditAction::ScreenDeleted,
            'resource_type' => 'screen',
            'resource_id' => 9,
            'ip_address' => '203.0.113.10',
        ]);
        AuditLog::factory()->create([
            'team_id' => $outsider->currentTeam->id,
            'user_id' => $outsider->id,
            'action' => AuditAction::ScreenDeleted,
            'resource_type' => 'screen',
            'resource_id' => 99,
        ]);

        $this->actingAs($member)
            ->withoutVite()
            ->get(route('audit-logs.index', $team))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('audit-logs.index', [$team, 'search' => '203.0.113.10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('audit-logs/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.ip_address', '203.0.113.10'));

        $csv = $this->actingAs($owner)
            ->get(route('audit-logs.export', [$team, 'action' => AuditAction::ScreenDeleted->value]));

        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('screen_deleted', $csv->streamedContent());
        $this->assertStringNotContainsString(',99,', $csv->streamedContent());
    }
}
