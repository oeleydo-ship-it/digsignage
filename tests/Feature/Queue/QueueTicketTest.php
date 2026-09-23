<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueNumberingReset;
use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use App\Support\QueueTicketNumbering;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_tickets_increments_the_display_number(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'code' => 'REG',
            'ticket_prefix' => 'A',
            'numbering_reset' => QueueNumberingReset::Daily,
        ]);

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
                'customer_name' => 'Ada',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'team_id' => $team->id,
            'queue_service_id' => $service->id,
            'number' => 'A001',
            'sequence' => 1,
            'status' => QueueTicketStatus::Waiting->value,
            'source' => QueueTicketSource::Staff->value,
            'queue_position' => 1,
        ]);

        $this->assertDatabaseHas('queue_tickets', [
            'number' => 'A002',
            'sequence' => 2,
            'queue_position' => 2,
            'customer_name' => 'Ada',
        ]);

        $service->refresh();
        $this->assertSame(3, $service->next_sequence);
        $this->assertSame(2, $service->last_issued);
        $this->assertSame(2, QueueTicket::query()->where('queue_service_id', $service->id)->count());
        $this->assertDatabaseHas('queue_ticket_events', [
            'type' => QueueTicketEventType::Created->value,
        ]);
    }

    public function test_daily_reset_allows_the_same_number_on_the_next_day(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
            'numbering_reset' => QueueNumberingReset::Daily,
            'sequence_period' => '2026-09-20',
            'next_sequence' => 2,
            'last_issued' => 1,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-20 10:00:00'));

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'number' => 'A002',
            'numbering_period' => '2026-09-20',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00:00'));

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'number' => 'A001',
            'numbering_period' => '2026-09-21',
        ]);

        $this->assertSame(
            1,
            QueueTicket::query()->where('number', 'A001')->where('numbering_period', '2026-09-21')->count(),
        );
        $this->assertSame(
            1,
            QueueTicket::query()->where('number', 'A002')->where('numbering_period', '2026-09-20')->count(),
        );
    }

    public function test_another_team_may_issue_the_same_number(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $serviceA = QueueService::factory()->create([
            'team_id' => $userA->currentTeam->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);
        $serviceB = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);

        $this->actingAs($userA)
            ->post(route('queue.tickets.store', $userA->currentTeam), [
                'queue_service_id' => $serviceA->id,
            ])
            ->assertRedirect();

        $this->actingAs($userB)
            ->post(route('queue.tickets.store', $userB->currentTeam), [
                'queue_service_id' => $serviceB->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, QueueTicket::query()->where('team_id', $userA->currentTeam->id)->where('number', 'A001')->count());
        $this->assertSame(1, QueueTicket::query()->where('team_id', $userB->currentTeam->id)->where('number', 'A001')->count());

        $this->actingAs($userA)
            ->get(route('queue.tickets', $userA->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/tickets')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.number', 'A001'));
    }

    public function test_members_cannot_issue_or_cancel_tickets(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
        ]);

        $this->actingAs($owner)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertRedirect();

        $ticket = QueueTicket::query()->firstOrFail();

        $this->actingAs($member)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('queue.tickets.cancel', [$team, $ticket]))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('queue.tickets', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.canViewQueue', true)
                ->where('permissions.canManageQueue', false)
                ->has('tickets.data', 1));
    }

    public function test_waiting_tickets_can_be_cancelled_and_positions_update(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
        ]);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->post(route('queue.tickets.store', $team), [
                    'queue_service_id' => $service->id,
                ])
                ->assertRedirect();
        }

        $first = QueueTicket::query()->where('number', 'A001')->firstOrFail();
        $second = QueueTicket::query()->where('number', 'A002')->firstOrFail();
        $third = QueueTicket::query()->where('number', 'A003')->firstOrFail();

        $this->assertSame(1, $first->queue_position);
        $this->assertSame(2, $second->queue_position);
        $this->assertSame(3, $third->queue_position);

        $this->actingAs($user)
            ->post(route('queue.tickets.cancel', [$team, $first]))
            ->assertRedirect();

        $this->assertSame(QueueTicketStatus::Cancelled, $first->fresh()->status);
        $this->assertNull($first->fresh()->queue_position);
        $this->assertSame(1, $second->fresh()->queue_position);
        $this->assertSame(2, $third->fresh()->queue_position);
        $this->assertDatabaseHas('queue_ticket_events', [
            'queue_ticket_id' => $first->id,
            'type' => QueueTicketEventType::StatusChanged->value,
        ]);

        $this->actingAs($user)
            ->get(route('queue.overview', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('operations.stats.waiting', 2)
                ->where('operations.stats.serving', 0)
                ->where('operations.stats.completed_today', 0));
    }

    public function test_duplicate_numbers_in_the_same_period_are_skipped(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'A',
            'numbering_reset' => QueueNumberingReset::Never,
            'sequence_period' => QueueTicketNumbering::NEVER_PERIOD,
        ]);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'numbering_period' => QueueTicketNumbering::NEVER_PERIOD,
        ]);

        $service->forceFill([
            'next_sequence' => 1,
            'last_issued' => null,
        ])->save();

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), [
                'queue_service_id' => $service->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('queue_tickets', [
            'queue_service_id' => $service->id,
            'number' => 'A002',
            'sequence' => 2,
        ]);

        $this->assertSame(2, QueueTicket::query()->where('queue_service_id', $service->id)->count());

        $this->expectException(UniqueConstraintViolationException::class);

        QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'numbering_period' => QueueTicketNumbering::NEVER_PERIOD,
        ]);
    }

    public function test_retried_ticket_issue_with_the_same_key_returns_one_ticket(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'R',
        ]);
        $payload = [
            'queue_service_id' => $service->id,
            'customer_name' => 'Retry Customer',
            'idempotency_key' => 'staff-request-42',
        ];

        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), $payload)
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('queue.tickets.store', $team), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseCount('queue_ticket_events', 1);
        $ticket = QueueTicket::query()->sole();
        $this->assertSame('R001', $ticket->number);
        $this->assertSame(hash('sha256', 'staff-request-42'), $ticket->idempotency_key);
        $this->assertSame(2, $service->fresh()->next_sequence);
    }

    public function test_distinct_issue_keys_create_distinct_tickets(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'ticket_prefix' => 'D',
        ]);

        foreach (['request-one', 'request-two'] as $key) {
            $this->actingAs($user)
                ->post(route('queue.tickets.store', $team), [
                    'queue_service_id' => $service->id,
                    'idempotency_key' => $key,
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('queue_tickets', 2);
        $this->assertSame(['D001', 'D002'], QueueTicket::query()->orderBy('id')->pluck('number')->all());
    }

    public function test_user_cannot_issue_a_ticket_for_another_teams_service(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $serviceB = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'ticket_prefix' => 'B',
        ]);

        $this->actingAs($userA)
            ->post(route('queue.tickets.store', $userA->currentTeam), [
                'queue_service_id' => $serviceB->id,
            ])
            ->assertSessionHasErrors('queue_service_id');
    }
}
