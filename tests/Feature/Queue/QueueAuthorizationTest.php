<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueCounterStatus;
use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueOpeningHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_roles_expose_the_expected_granular_permissions(): void
    {
        $this->assertTrue(TeamRole::BranchManager->hasPermission(TeamPermission::ManageQueueSettings));
        $this->assertTrue(TeamRole::QueueSupervisor->hasPermission(TeamPermission::ManageQueueCounters));
        $this->assertTrue(TeamRole::QueueSupervisor->hasPermission(TeamPermission::ViewQueueReports));
        $this->assertFalse(TeamRole::QueueSupervisor->hasPermission(TeamPermission::ManageQueueSettings));
        $this->assertTrue(TeamRole::CounterStaff->hasPermission(TeamPermission::CallQueue));
        $this->assertTrue(TeamRole::CounterStaff->hasPermission(TeamPermission::TransferQueue));
        $this->assertTrue(TeamRole::CounterStaff->hasPermission(TeamPermission::CompleteQueue));
        $this->assertFalse(TeamRole::CounterStaff->hasPermission(TeamPermission::CancelQueue));
        $this->assertTrue(TeamRole::ReportingUser->hasPermission(TeamPermission::ViewQueueReports));
        $this->assertFalse(TeamRole::ReportingUser->hasPermission(TeamPermission::CallQueue));

        $assignable = collect(TeamRole::assignable())->pluck('value');
        $this->assertTrue($assignable->contains(TeamRole::BranchManager->value));
        $this->assertTrue($assignable->contains(TeamRole::QueueSupervisor->value));
        $this->assertTrue($assignable->contains(TeamRole::CounterStaff->value));
        $this->assertTrue($assignable->contains(TeamRole::ReportingUser->value));
    }

    public function test_queue_supervisor_can_manage_operations_but_not_settings(): void
    {
        [$supervisor, $team] = $this->memberWithRole(TeamRole::QueueSupervisor);

        $this->actingAs($supervisor)
            ->post(route('queue.services.store', $team), $this->servicePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('queue_services', [
            'team_id' => $team->id,
            'code' => 'REG',
        ]);

        $this->actingAs($supervisor)
            ->patch(route('queue.settings.update', $team), [
                'max_priority_wait_seconds' => 60,
                'promote_after_seconds' => null,
            ])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('queue.reports', $team))
            ->assertOk();
    }

    public function test_assigned_counter_staff_can_call_transfer_and_complete_but_not_cancel(): void
    {
        [$staff, $team] = $this->memberWithRole(TeamRole::CounterStaff);
        $source = QueueService::factory()->create(['team_id' => $team->id, 'name' => 'Registration']);
        $destination = QueueService::factory()->create(['team_id' => $team->id, 'name' => 'Payments']);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'assigned_user_id' => $staff->id,
            'status' => QueueCounterStatus::Open,
        ]);
        $counter->services()->attach($source);
        $first = QueueTicket::factory()->forService($source)->create(['number' => 'A001']);

        $this->actingAs($staff)
            ->get(route('queue.counters', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canCallQueue', true)
                ->where('permissions.canTransferQueue', true)
                ->where('permissions.canCompleteQueue', true)
                ->where('permissions.canCancelQueue', false)
                ->where('permissions.canManageCounters', false)
                ->where('permissions.canViewReports', false)
                ->where('permissions.canManageSettings', false));

        $this->actingAs($staff)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect();
        $this->assertSame(QueueTicketStatus::Serving, $first->fresh()->status);

        $this->actingAs($staff)
            ->post(route('queue.counters.transfer', [$team, $counter]), [
                'queue_service_id' => $destination->id,
                'counter_id' => null,
                'reason' => 'Payment required',
            ])
            ->assertRedirect();
        $this->assertSame($destination->id, $first->fresh()->queue_service_id);
        $this->assertSame(QueueTicketStatus::Waiting, $first->status);

        $second = QueueTicket::factory()->forService($source)->create(['number' => 'A002']);
        $this->actingAs($staff)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect();
        $this->actingAs($staff)
            ->post(route('queue.counters.complete', [$team, $counter]))
            ->assertRedirect();
        $this->assertSame(QueueTicketStatus::Completed, $second->fresh()->status);

        $third = QueueTicket::factory()->forService($source)->create(['number' => 'A003']);
        $this->actingAs($staff)
            ->post(route('queue.tickets.cancel', [$team, $third]))
            ->assertForbidden();
        $this->assertSame(QueueTicketStatus::Waiting, $third->fresh()->status);
    }

    public function test_reporting_user_can_view_reports_but_cannot_operate_an_assigned_counter(): void
    {
        [$reporter, $team] = $this->memberWithRole(TeamRole::ReportingUser);
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'assigned_user_id' => $reporter->id,
        ]);
        $counter->services()->attach($service);
        QueueTicket::factory()->forService($service)->create(['number' => 'R001']);

        $this->actingAs($reporter)
            ->get(route('queue.reports', $team))
            ->assertOk();

        $this->actingAs($reporter)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertForbidden();

        $this->actingAs($reporter)
            ->post(route('queue.counters.store', $team), [])
            ->assertForbidden();
    }

    /** @return array{User, Team} */
    protected function memberWithRole(TeamRole $role): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => $role->value]);
        $member->switchTeam($team);

        return [$member, $team];
    }

    /** @return array<string, mixed> */
    protected function servicePayload(): array
    {
        return [
            'name' => 'Registration',
            'code' => 'REG',
            'ticket_prefix' => 'A',
            'description' => null,
            'location_id' => null,
            'opening_hours' => QueueOpeningHours::defaults(),
            'average_service_duration_seconds' => 300,
            'max_queue_capacity' => 50,
            'numbering_reset' => QueueNumberingReset::Daily->value,
            'default_priority' => 0,
            'priority_rules' => [],
            'display_color' => '#2563eb',
            'queue_strategy' => QueueStrategy::Fifo->value,
            'is_active' => true,
        ];
    }
}
