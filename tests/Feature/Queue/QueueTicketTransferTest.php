<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketStatus;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueTicketTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_keeps_the_ticket_number_and_journey_events(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        [$registration, $billing] = $this->services($team->id);
        $counter = $this->counter($team->id, $registration->id);
        $ticket = QueueTicket::factory()->forService($registration)->create([
            'number' => 'A105',
            'sequence' => 105,
        ]);

        QueueTicketEvent::factory()->create([
            'team_id' => $team->id,
            'queue_ticket_id' => $ticket->id,
            'type' => QueueTicketEventType::Created,
            'payload' => ['number' => 'A105'],
        ]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]));
        $this->actingAs($user)
            ->post(route('queue.counters.transfer', [$team, $counter]), [
                'queue_service_id' => $billing->id,
                'reason' => 'Registration complete',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('A105', $ticket->number);
        $this->assertSame($ticket->id, QueueTicket::query()->where('number', 'A105')->value('id'));
        $this->assertSame($billing->id, $ticket->queue_service_id);
        $this->assertSame(QueueTicketStatus::Waiting, $ticket->status);
        $this->assertNull($ticket->counter_id);
        $this->assertSame(1, $ticket->queue_position);

        $this->assertDatabaseHas('queue_ticket_events', [
            'queue_ticket_id' => $ticket->id,
            'type' => QueueTicketEventType::Created->value,
        ]);
        $this->assertDatabaseHas('queue_ticket_events', [
            'queue_ticket_id' => $ticket->id,
            'type' => QueueTicketEventType::Transferred->value,
            'user_id' => $user->id,
        ]);

        $transfer = QueueTicketEvent::query()
            ->where('queue_ticket_id', $ticket->id)
            ->where('type', QueueTicketEventType::Transferred)
            ->firstOrFail();

        $this->assertSame($registration->id, $transfer->payload['previous_service_id']);
        $this->assertSame($billing->id, $transfer->payload['new_service_id']);
        $this->assertSame($counter->id, $transfer->payload['previous_counter_id']);
        $this->assertNull($transfer->payload['new_counter_id']);
        $this->assertSame('Registration complete', $transfer->payload['reason']);
    }

    public function test_two_sequential_transfers_preserve_history(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        [$registration, $billing, $pharmacy] = $this->services($team->id, withPharmacy: true);
        $registrationDesk = $this->counter($team->id, $registration->id, 'REG');
        $billingDesk = $this->counter($team->id, $billing->id, 'BIL');
        $ticket = QueueTicket::factory()->forService($registration)->create([
            'number' => 'A105',
            'sequence' => 105,
        ]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $registrationDesk]));
        $this->actingAs($user)->post(route('queue.counters.transfer', [$team, $registrationDesk]), [
            'queue_service_id' => $billing->id,
            'reason' => 'To billing',
        ]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $billingDesk]));
        $this->actingAs($user)->post(route('queue.counters.transfer', [$team, $billingDesk]), [
            'queue_service_id' => $pharmacy->id,
            'counter_id' => $this->counter($team->id, $pharmacy->id, 'PHM')->id,
            'reason' => 'To pharmacy',
        ]);

        $ticket->refresh();
        $this->assertSame('A105', $ticket->number);
        $this->assertSame($pharmacy->id, $ticket->queue_service_id);
        $this->assertSame(QueueTicketStatus::Waiting, $ticket->status);
        $this->assertSame(
            2,
            QueueTicketEvent::query()
                ->where('queue_ticket_id', $ticket->id)
                ->where('type', QueueTicketEventType::Transferred)
                ->count(),
        );
    }

    public function test_completed_tickets_cannot_be_transferred(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        [$registration, $billing] = $this->services($team->id);
        $counter = $this->counter($team->id, $registration->id);
        QueueTicket::factory()->forService($registration)->create(['number' => 'A001', 'sequence' => 1]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]));
        $this->actingAs($user)->post(route('queue.counters.complete', [$team, $counter]));

        $this->actingAs($user)
            ->post(route('queue.counters.transfer', [$team, $counter]), [
                'queue_service_id' => $billing->id,
                'reason' => 'Too late',
            ])
            ->assertSessionHasErrors('ticket');

        $this->assertSame(
            QueueTicketStatus::Completed,
            QueueTicket::query()->where('number', 'A001')->first()?->status,
        );
        $this->assertSame(0, QueueTicketEvent::query()->where('type', QueueTicketEventType::Transferred)->count());
    }

    public function test_transfers_are_isolated_between_teams(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        [$registration] = $this->services($userA->currentTeam->id);
        $billingB = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'name' => 'Billing',
            'code' => 'BIL',
            'ticket_prefix' => 'B',
        ]);
        $counter = $this->counter($userA->currentTeam->id, $registration->id);
        QueueTicket::factory()->forService($registration)->create();

        $this->actingAs($userA)->post(route('queue.counters.call-next', [$userA->currentTeam, $counter]));
        $this->actingAs($userA)
            ->post(route('queue.counters.transfer', [$userA->currentTeam, $counter]), [
                'queue_service_id' => $billingB->id,
                'reason' => 'Cross tenant',
            ])
            ->assertSessionHasErrors('queue_service_id');
    }

    public function test_call_next_after_transfer_still_assigns_unique_tickets(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        [$registration, $billing] = $this->services($team->id);
        $registrationDesk = $this->counter($team->id, $registration->id, 'REG');
        $billingA = $this->counter($team->id, $billing->id, 'BA');
        $billingB = $this->counter($team->id, $billing->id, 'BB');

        $transferred = QueueTicket::factory()->forService($registration)->create([
            'number' => 'A105',
            'sequence' => 105,
            'created_at' => now()->subMinutes(3),
        ]);
        QueueTicket::factory()->forService($billing)->create([
            'number' => 'B041',
            'sequence' => 41,
            'created_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $registrationDesk]));
        $this->actingAs($user)->post(route('queue.counters.transfer', [$team, $registrationDesk]), [
            'queue_service_id' => $billing->id,
            'reason' => 'To billing',
        ]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $billingA]));
        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $billingB]));

        $numbers = [
            $billingA->fresh()->currentTicket()?->number,
            $billingB->fresh()->currentTicket()?->number,
        ];

        $this->assertEqualsCanonicalizing(['A105', 'B041'], $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertContains($transferred->fresh()->status, [
            QueueTicketStatus::Serving,
            QueueTicketStatus::Called,
        ]);
    }

    public function test_tickets_page_includes_the_journey_timeline(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        [$registration, $billing] = $this->services($team->id);
        $counter = $this->counter($team->id, $registration->id);
        $ticket = QueueTicket::factory()->forService($registration)->create(['number' => 'A105', 'sequence' => 105]);

        $this->actingAs($user)->post(route('queue.counters.call-next', [$team, $counter]));
        $this->actingAs($user)->post(route('queue.counters.transfer', [$team, $counter]), [
            'queue_service_id' => $billing->id,
            'reason' => 'To billing',
        ]);

        $this->actingAs($user)
            ->get(route('queue.tickets', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/tickets')
                ->where('tickets.data.0.number', 'A105')
                ->where('tickets.data.0.service_name', 'Billing')
                ->has('tickets.data.0.events'));

        $this->assertSame($ticket->id, QueueTicket::query()->where('number', 'A105')->value('id'));
    }

    /**
     * @return array{0: QueueService, 1: QueueService, 2?: QueueService}
     */
    protected function services(int $teamId, bool $withPharmacy = false): array
    {
        $registration = QueueService::factory()->create([
            'team_id' => $teamId,
            'name' => 'Registration',
            'code' => 'REG',
            'ticket_prefix' => 'A',
        ]);
        $billing = QueueService::factory()->create([
            'team_id' => $teamId,
            'name' => 'Billing',
            'code' => 'BIL',
            'ticket_prefix' => 'B',
        ]);

        if (! $withPharmacy) {
            return [$registration, $billing];
        }

        $pharmacy = QueueService::factory()->create([
            'team_id' => $teamId,
            'name' => 'Pharmacy',
            'code' => 'PHM',
            'ticket_prefix' => 'P',
        ]);

        return [$registration, $billing, $pharmacy];
    }

    protected function counter(int $teamId, int $serviceId, string $code = 'C01'): QueueCounter
    {
        $counter = QueueCounter::factory()->create([
            'team_id' => $teamId,
            'code' => $code,
        ]);
        $counter->services()->attach($serviceId);

        return $counter;
    }
}
