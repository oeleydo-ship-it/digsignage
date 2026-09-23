<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Models\Location;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueVirtualTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_workspace_location_and_service_join_links(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create([
            'team_id' => $team->id,
            'name' => 'Dubai Lobby',
        ]);
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Registration',
        ]);
        QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Billing',
        ]);

        $this->get(route('queue.virtual.show', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/virtual-join')
                ->where('team.name', $team->name)
                ->has('services', 2));

        $this->get(route('queue.virtual.show', [
            'team' => $team,
            'location' => $location->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('location.name', 'Dubai Lobby')
                ->has('services', 1)
                ->where('services.0.name', 'Registration'));

        $this->get(route('queue.virtual.show', [
            'team' => $team,
            'service' => $service->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('services', 1)
                ->where('services.0.id', $service->id));
    }

    public function test_guest_can_join_and_receives_an_opaque_tracking_url(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'ticket_prefix' => 'A',
            'average_service_duration_seconds' => 180,
        ]);

        $response = $this->post(route('queue.virtual.store', $team), [
            'queue_service_id' => $service->id,
            'customer_name' => 'Guest',
        ]);

        $ticket = QueueTicket::query()->sole();

        $this->assertSame(QueueTicketSource::Qr, $ticket->source);
        $this->assertSame(QueueTicketStatus::Waiting, $ticket->status);
        $this->assertSame('Guest', $ticket->customer_name);
        $this->assertNotNull($ticket->public_token);
        $this->assertGreaterThanOrEqual(40, strlen($ticket->public_token));
        $response->assertRedirect(route('queue.virtual.ticket', $ticket->public_token));
    }

    public function test_tracking_page_shows_position_estimate_and_now_serving(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'average_service_duration_seconds' => 120,
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'A001',
            'sequence' => 1,
            'status' => QueueTicketStatus::Serving,
            'queue_position' => null,
            'called_at' => now(),
        ]);
        $ticket = QueueTicket::factory()->forService($service)->create([
            'number' => 'A002',
            'sequence' => 2,
            'status' => QueueTicketStatus::Waiting,
            'queue_position' => 2,
            'source' => QueueTicketSource::Qr,
            'public_token' => str_repeat('x', 48),
        ]);

        $this->get(route('queue.virtual.ticket', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/virtual-ticket')
                ->where('ticket.number', 'A002')
                ->where('ticket.position', 2)
                ->where('ticket.people_ahead', 1)
                ->where('ticket.estimated_wait_minutes', 2)
                ->where('ticket.now_serving.number', 'A001')
                ->where('ticket.can_cancel', true));
    }

    public function test_guest_can_cancel_their_waiting_virtual_ticket(): void
    {
        $service = QueueService::factory()->create();
        $ticket = QueueTicket::factory()->forService($service)->create([
            'source' => QueueTicketSource::Qr,
            'public_token' => str_repeat('v', 48),
        ]);

        $this->post(route('queue.virtual.cancel', $ticket->public_token))
            ->assertRedirect();

        $this->assertSame(
            QueueTicketStatus::Cancelled,
            $ticket->fresh()->status,
        );
    }

    public function test_virtual_join_rejects_a_service_from_another_team(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $foreign = QueueService::factory()->create([
            'team_id' => $userB->currentTeam->id,
        ]);

        $this->post(route('queue.virtual.store', $userA->currentTeam), [
            'queue_service_id' => $foreign->id,
        ])->assertSessionHasErrors('queue_service_id');

        $this->assertDatabaseCount('queue_tickets', 0);
    }
}
