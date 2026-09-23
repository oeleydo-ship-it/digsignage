<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\ClaimRankedQueueTicket;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueCounterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Counter 01',
            'code' => 'C01',
            'location_id' => null,
            'assigned_user_id' => null,
            'status' => QueueCounterStatus::Open->value,
            'service_ids' => [],
        ], $overrides);
    }

    public function test_owners_can_create_and_list_counters(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'code' => 'REG',
        ]);

        $this->actingAs($user)
            ->post(route('queue.counters.store', $team), $this->payload([
                'service_ids' => [$service->id],
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('queue.counters', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/counters')
                ->has('counters', 1)
                ->where('counters.0.name', 'Counter 01')
                ->where('counters.0.code', 'C01')
                ->where('counters.0.status', 'open')
                ->where('counters.0.services.0.name', 'Registration')
                ->where('permissions.canManageQueue', true));

        $this->assertDatabaseHas('queue_counters', [
            'team_id' => $team->id,
            'code' => 'C01',
        ]);
        $this->assertDatabaseHas('queue_counter_service', [
            'queue_service_id' => $service->id,
        ]);
    }

    public function test_code_is_unique_per_team(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);

        QueueCounter::factory()->create([
            'team_id' => $team->id,
            'code' => 'C01',
        ]);

        $this->actingAs($user)
            ->post(route('queue.counters.store', $team), $this->payload([
                'service_ids' => [$service->id],
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_owner_can_create_a_counter_assigned_to_themselves(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Billing',
            'code' => 'BIL',
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('queue.counters.store', $team), $this->payload([
                'name' => 'Counter 2',
                'code' => 'C02',
                'assigned_user_id' => $user->id,
                'service_ids' => [$service->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('queue.counters', $team));

        $this->assertDatabaseHas('queue_counters', [
            'team_id' => $team->id,
            'name' => 'Counter 2',
            'code' => 'C02',
            'assigned_user_id' => $user->id,
        ]);

        $counter = QueueCounter::query()->where('team_id', $team->id)->where('code', 'C02')->firstOrFail();

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->patch(route('queue.counters.update', [$team, $counter]), $this->payload([
                'name' => 'Counter 2 Updated',
                'code' => 'C02',
                'assigned_user_id' => $user->id,
                'service_ids' => [$service->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('queue.counters', $team));

        $this->assertDatabaseHas('queue_counters', [
            'id' => $counter->id,
            'name' => 'Counter 2 Updated',
        ]);
    }

    public function test_another_team_may_reuse_the_same_counter_code(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $serviceB = QueueService::factory()->create(['team_id' => $userB->currentTeam->id]);

        QueueCounter::factory()->create([
            'team_id' => $userA->currentTeam->id,
            'code' => 'C01',
        ]);

        $this->actingAs($userB)
            ->post(route('queue.counters.store', $userB->currentTeam), $this->payload([
                'service_ids' => [$serviceB->id],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('queue_counters', [
            'team_id' => $userB->currentTeam->id,
            'code' => 'C01',
        ]);
    }

    public function test_members_cannot_manage_counters(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        $service = QueueService::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('queue.counters.store', $team), $this->payload([
                'service_ids' => [$service->id],
            ]))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('queue.counters', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/counters')
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', false));
    }

    public function test_counters_are_isolated_between_teams(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $counter = QueueCounter::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'code' => 'THEIRS',
        ]);

        $this->actingAs($userA)
            ->get(route('queue.counters', $userA->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('counters', 0));

        $this->actingAs($userA)
            ->patch(route('queue.counters.update', [$userA->currentTeam, $counter]), $this->payload([
                'name' => 'Hijacked',
                'code' => 'HACK',
                'service_ids' => [999],
            ]))
            ->assertForbidden();
    }

    public function test_desk_call_next_completes_the_current_ticket_and_calls_the_next_one(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'ticket_prefix' => 'A',
        ]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'name' => 'Counter 01',
            'code' => 'C01',
        ]);
        $counter->services()->attach($service->id);

        $first = QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'created_at' => now()->subMinutes(5),
        ]);
        $second = QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'created_at' => now()->subMinutes(1),
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect(route('queue.counters.desk', [$team, $counter]));

        $first->refresh();
        $this->assertSame(QueueTicketStatus::Serving, $first->status);
        $this->assertSame($counter->id, $first->counter_id);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect(route('queue.counters.desk', [$team, $counter]));

        $first->refresh();
        $this->assertSame(QueueTicketStatus::Completed, $first->status);
        $this->assertNotNull($first->completed_at);
        $this->assertSame(QueueTicketStatus::Serving, $second->fresh()->status);
        $this->assertSame($second->id, $counter->fresh()->currentTicket()?->id);
        $this->assertSame(QueueCounterStatus::Busy, $counter->fresh()->status);
        $this->actingAs($user)
            ->get(route('queue.counters.desk', [$team, $counter]))
            ->assertInertia(fn (Assert $page) => $page->where('current.number', 'A002'));
    }

    public function test_desk_status_reports_a_new_waiting_ticket_without_reloading_the_page(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service->id);

        $this->actingAs($user)
            ->getJson(route('queue.counters.desk.status', [$team, $counter]))
            ->assertOk()
            ->assertJsonPath('waiting_count', 0);

        QueueTicket::factory()->forService($service)->create(['number' => 'A001']);

        $this->actingAs($user)
            ->getJson(route('queue.counters.desk.status', [$team, $counter]))
            ->assertOk()
            ->assertJsonPath('waiting_count', 1)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_call_next_keeps_the_current_ticket_when_nobody_is_waiting(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service->id);
        $ticket = QueueTicket::factory()->forService($service)->create(['number' => 'A001']);

        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect(route('queue.counters.desk', [$team, $counter]));
        $this->actingAs($user)
            ->from(route('dashboard', $team))
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertSessionHasErrors('ticket')
            ->assertRedirect(route('queue.counters.desk', [$team, $counter]));

        $this->assertSame(QueueTicketStatus::Serving, $ticket->fresh()->status);
        $this->assertNull($ticket->completed_at);
        $this->assertSame($ticket->id, $counter->fresh()->currentTicket()?->id);
    }

    public function test_two_counters_never_receive_the_same_ticket(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
        ]);
        $counterA = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'A']);
        $counterB = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'B']);
        $counterA->services()->attach($service->id);
        $counterB->services()->attach($service->id);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A101',
            'sequence' => 101,
            'created_at' => now()->subMinutes(2),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A102',
            'sequence' => 102,
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $counterA]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $counterB]))
            ->assertRedirect();

        $numbers = [
            $counterA->fresh()->currentTicket()?->number,
            $counterB->fresh()->currentTicket()?->number,
        ];

        $this->assertEqualsCanonicalizing(['A101', 'A102'], $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertSame(1, QueueTicket::query()->where('number', 'A101')->where('status', QueueTicketStatus::Serving)->count());
        $this->assertSame(1, QueueTicket::query()->where('number', 'A102')->where('status', QueueTicketStatus::Serving)->count());
    }

    public function test_two_workers_with_the_same_stale_snapshot_claim_different_tickets(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counterA = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'A']);
        $counterB = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'B']);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A101',
            'sequence' => 101,
            'created_at' => now()->subMinutes(2),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A102',
            'sequence' => 102,
            'created_at' => now()->subMinute(),
        ]);

        // Both workers selected before either update committed, so each holds
        // the same candidate order: A101, then A102.
        $workerA = QueueTicket::query()->where('queue_service_id', $service->id)->orderBy('id')->get();
        $workerB = QueueTicket::query()->where('queue_service_id', $service->id)->orderBy('id')->get();
        $claim = app(ClaimRankedQueueTicket::class);

        $first = $claim->handle($workerA, $counterA, $user->id, now());
        $second = $claim->handle($workerB, $counterB, $user->id, now());

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('A101', $first['ticket']->number);
        $this->assertSame('A102', $second['ticket']->number);
        $this->assertNotSame($first['ticket']->id, $second['ticket']->id);
        $this->assertSame($counterA->id, $first['ticket']->counter_id);
        $this->assertSame($counterB->id, $second['ticket']->counter_id);
        $this->assertSame(2, QueueTicket::query()->where('status', QueueTicketStatus::Serving)->count());
    }

    public function test_call_next_skips_a_ticket_already_claimed_by_another_counter(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id, 'ticket_prefix' => 'A']);
        $counterA = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'A']);
        $counterB = QueueCounter::factory()->create(['team_id' => $team->id, 'code' => 'B']);
        $counterA->services()->attach($service->id);
        $counterB->services()->attach($service->id);

        $claimed = QueueTicket::factory()->forService($service)->create([
            'number' => 'A101',
            'sequence' => 101,
            'status' => QueueTicketStatus::Serving,
            'counter_id' => $counterA->id,
            'called_at' => now(),
            'service_started_at' => now(),
            'created_at' => now()->subMinutes(2),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A102',
            'sequence' => 102,
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $counterB]))
            ->assertRedirect();

        $this->assertSame($counterA->id, $claimed->fresh()->counter_id);
        $this->assertSame('A102', $counterB->fresh()->currentTicket()?->number);
        $this->assertNotSame($claimed->id, $counterB->fresh()->currentTicket()?->id);
    }

    public function test_assigned_member_can_operate_desk_unassigned_cannot(): void
    {
        $owner = User::factory()->create();
        $assigned = User::factory()->create();
        $other = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($assigned, ['role' => TeamRole::Member->value]);
        $team->members()->attach($other, ['role' => TeamRole::Member->value]);
        $assigned->switchTeam($team);
        $other->switchTeam($team);

        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'assigned_user_id' => $assigned->id,
        ]);
        $counter->services()->attach($service->id);
        QueueTicket::factory()->forService($service)->create();

        $this->actingAs($assigned)
            ->get(route('queue.counters.desk', [$team, $counter]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('queue/desk'));

        $this->actingAs($assigned)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect();

        $this->actingAs($other)
            ->get(route('queue.counters.desk', [$team, $counter]))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertForbidden();
    }

    public function test_complete_hold_and_no_show_free_the_desk(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($service->id);
        QueueTicket::factory()->forService($service)->create(['number' => 'A001', 'sequence' => 1]);

        $this->actingAs($user)
            ->post(route('queue.counters.call-next', [$team, $counter]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('queue.counters.complete', [$team, $counter]))
            ->assertRedirect();

        $this->assertSame(QueueTicketStatus::Completed, QueueTicket::query()->where('number', 'A001')->first()?->status);
        $this->assertNull($counter->fresh()->currentTicket());

        QueueTicket::factory()->forService($service)->create(['number' => 'A002', 'sequence' => 2]);
        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]));
        $this->actingAs($user)->post(route('queue.counters.hold', [$team, $counter]));
        $this->assertSame(QueueTicketStatus::OnHold, QueueTicket::query()->where('number', 'A002')->first()?->status);

        $this->actingAs($user)->post(route('queue.counters.resume', [$team, $counter]));
        $this->assertSame(QueueTicketStatus::Serving, QueueTicket::query()->where('number', 'A002')->first()?->status);

        $this->actingAs($user)->post(route('queue.counters.no-show', [$team, $counter]));
        $this->assertSame(QueueTicketStatus::NoShow, QueueTicket::query()->where('number', 'A002')->first()?->status);
    }

    public function test_transfer_moves_the_ticket_to_another_service(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $registration = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);
        $billing = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Billing',
            'code' => 'BIL',
            'ticket_prefix' => 'B',
        ]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($registration->id);
        $ticket = QueueTicket::factory()->forService($registration)->create();

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]));
        $this->actingAs($user)
            ->post(route('queue.counters.transfer', [$team, $counter]), [
                'queue_service_id' => $billing->id,
                'reason' => 'Proceed to billing',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame($billing->id, $ticket->queue_service_id);
        $this->assertSame(QueueTicketStatus::Waiting, $ticket->status);
        $this->assertNull($ticket->counter_id);
        $this->assertDatabaseHas('queue_ticket_events', [
            'queue_ticket_id' => $ticket->id,
            'type' => 'transferred',
            'user_id' => $user->id,
        ]);
    }
}
