<?php

namespace Tests\Feature\Queue;

use App\Enums\AuditAction;
use App\Enums\QueueCounterStatus;
use App\Models\AuditLog;
use App\Models\QueueCounter;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_actions_record_actor_ip_and_non_pii_before_after_values(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
        ]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])
            ->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
                'customer_name' => 'Private Customer',
                'customer_email' => 'private@example.test',
            ])
            ->assertRedirect();

        $ticket = QueueTicket::query()->where('team_id', $team->id)->firstOrFail();
        $created = $this->audit($ticket, AuditAction::QueueTicketCreated);

        $this->assertSame($user->id, $created->user_id);
        $this->assertSame('198.51.100.22', $created->ip_address);
        $this->assertNull($created->before);
        $this->assertSame('waiting', $created->after['status']);
        $this->assertArrayNotHasKey('customer_name', $created->after);
        $this->assertArrayNotHasKey('customer_email', $created->after);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]))->assertRedirect();
        $called = $this->audit($ticket, AuditAction::QueueTicketCalled);
        $this->assertSame('waiting', $called->before['status']);
        $this->assertSame('serving', $called->after['status']);
        $this->assertSame($counter->id, $called->after['counter_id']);

        $this->actingAs($user)->post(route('queue.counters.recall', [$team, $counter]))->assertRedirect();
        $this->actingAs($user)->post(route('queue.counters.complete', [$team, $counter]))->assertRedirect();

        $this->assertSame(
            $user->id,
            $this->audit($ticket, AuditAction::QueueTicketRecalled)->user_id,
        );
        $completed = $this->audit($ticket, AuditAction::QueueTicketCompleted);
        $this->assertSame('serving', $completed->before['status']);
        $this->assertSame('completed', $completed->after['status']);

        $this->actingAs($user)->post(route('queue.tickets.store', $team), [
            'queue_service_id' => $service->id,
        ])->assertRedirect();
        $cancelledTicket = QueueTicket::query()->latest('id')->firstOrFail();
        $this->actingAs($user)
            ->post(route('queue.tickets.cancel', [$team, $cancelledTicket]))
            ->assertRedirect();

        $cancelled = $this->audit($cancelledTicket, AuditAction::QueueTicketCancelled);
        $this->assertSame('waiting', $cancelled->before['status']);
        $this->assertSame('cancelled', $cancelled->after['status']);
    }

    public function test_transfer_hold_resume_and_no_show_are_audited(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $firstService = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'FIRST']);
        $secondService = QueueService::factory()->create(['team_id' => $team->id, 'code' => 'SECOND']);
        $firstCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'FIRST']);
        $secondCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'SECOND']);
        $firstCounter->services()->attach($firstService);
        $secondCounter->services()->attach($secondService);
        $ticket = QueueTicket::factory()->forService($firstService)->create();

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $firstCounter]));
        $this->actingAs($user)->post(route('queue.counters.transfer', [$team, $firstCounter]), [
            'queue_service_id' => $secondService->id,
            'counter_id' => $secondCounter->id,
            'reason' => 'Continue at second desk',
        ])->assertRedirect();

        $transferred = $this->audit($ticket, AuditAction::QueueTicketTransferred);
        $this->assertSame($firstService->id, $transferred->before['queue_service_id']);
        $this->assertSame($secondService->id, $transferred->after['queue_service_id']);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $secondCounter]));
        $this->actingAs($user)->post(route('queue.counters.hold', [$team, $secondCounter]))->assertRedirect();
        $this->actingAs($user)->post(route('queue.counters.resume', [$team, $secondCounter]))->assertRedirect();
        $this->actingAs($user)->post(route('queue.counters.no-show', [$team, $secondCounter]))->assertRedirect();

        $this->assertSame('on_hold', $this->audit($ticket, AuditAction::QueueTicketHeld)->after['status']);
        $this->assertSame('serving', $this->audit($ticket, AuditAction::QueueTicketResumed)->after['status']);
        $this->assertSame('no_show', $this->audit($ticket, AuditAction::QueueTicketNoShow)->after['status']);
    }

    public function test_priority_changes_and_counter_open_close_transitions_are_audited(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('queue.priorities.store', $team), [
            'name' => 'Urgent',
            'code' => 'urgent',
            'weight' => 50,
            'color' => '#DC2626',
        ])->assertRedirect();

        $priority = QueuePriority::query()->where('team_id', $team->id)->where('code', 'urgent')->firstOrFail();
        $priorityAudit = AuditLog::query()
            ->where('resource_type', 'queue_priority')
            ->where('resource_id', $priority->id)
            ->where('action', AuditAction::QueuePriorityChanged)
            ->firstOrFail();
        $this->assertNull($priorityAudit->before);
        $this->assertSame(50, $priorityAudit->after['weight']);

        $this->actingAs($user)->post(route('queue.counters.store', $team), [
            'name' => 'Counter 01',
            'code' => 'C01',
            'status' => QueueCounterStatus::Closed->value,
            'service_ids' => [$service->id],
        ])->assertRedirect();

        $counter = QueueCounter::query()->where('team_id', $team->id)->where('code', 'C01')->firstOrFail();
        $this->assertSame(
            1,
            AuditLog::query()->where('resource_type', 'queue_counter')
                ->where('resource_id', $counter->id)
                ->where('action', AuditAction::QueueCounterClosed)
                ->count(),
        );

        $this->actingAs($user)->patch(route('queue.counters.update', [$team, $counter]), [
            'name' => 'Counter 01',
            'code' => 'C01',
            'status' => QueueCounterStatus::Open->value,
            'service_ids' => [$service->id],
        ])->assertRedirect();

        $opened = AuditLog::query()
            ->where('resource_type', 'queue_counter')
            ->where('resource_id', $counter->id)
            ->where('action', AuditAction::QueueCounterOpened)
            ->firstOrFail();
        $this->assertSame('closed', $opened->before['status']);
        $this->assertSame('open', $opened->after['status']);
        $this->assertSame([$service->id], $opened->after['service_ids']);
    }

    protected function audit(QueueTicket $ticket, AuditAction $action): AuditLog
    {
        return AuditLog::query()
            ->where('team_id', $ticket->team_id)
            ->where('resource_type', 'queue_ticket')
            ->where('resource_id', $ticket->id)
            ->where('action', $action)
            ->latest('id')
            ->firstOrFail();
    }
}
