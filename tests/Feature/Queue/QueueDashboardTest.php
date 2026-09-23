<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\SaveQueueCounter;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Events\QueueUpdated;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_dashboard_reports_live_metrics_and_groupings(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $location = Location::factory()->create(['team_id' => $team->id, 'name' => 'Main Branch']);
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Registration',
        ]);
        $openCounter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'status' => QueueCounterStatus::Open,
            'name' => 'Counter One',
        ]);
        $closedCounter = QueueCounter::factory()->closed()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Counter Two',
        ]);
        $openCounter->services()->attach($service);
        $closedCounter->services()->attach($service);

        $longest = QueueTicket::factory()->forService($service)->create([
            'number' => 'R001',
            'queue_position' => 1,
            'created_at' => now()->subMinutes(10),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R002',
            'queue_position' => 2,
            'created_at' => now()->subMinutes(5),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R003',
            'status' => QueueTicketStatus::Serving,
            'counter_id' => $openCounter->id,
            'waiting_duration_seconds' => 120,
            'called_at' => now()->subMinutes(4),
            'service_started_at' => now()->subMinutes(3),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R004',
            'status' => QueueTicketStatus::Completed,
            'waiting_duration_seconds' => 180,
            'serving_duration_seconds' => 240,
            'completed_at' => now()->subHour(),
        ]);
        QueueTicket::factory()->forService($service)->create([
            'number' => 'R005',
            'status' => QueueTicketStatus::NoShow,
            'waiting_duration_seconds' => 60,
            'completed_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('queue.overview', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/overview')
                ->where('operations.stats.waiting', 2)
                ->where('operations.stats.serving', 1)
                ->where('operations.stats.completed_today', 1)
                ->where('operations.stats.no_shows_today', 1)
                ->where('operations.stats.average_wait_seconds', 252)
                ->where('operations.stats.average_service_seconds', 240)
                ->where('operations.stats.longest_waiting.id', $longest->id)
                ->where('operations.stats.open_counters', 1)
                ->where('operations.stats.closed_counters', 1)
                ->where('operations.locations.0.name', 'Main Branch')
                ->where('operations.locations.0.congestion.level', 'moderate')
                ->where('operations.services.0.name', 'Registration')
                ->where('operations.counters.0.waiting', 2)
                ->has('operations.service_ids', 1)
                ->has('operations.refreshed_at'));
    }

    public function test_dashboard_metrics_are_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = QueueService::factory()->create(['team_id' => $other->currentTeam->id]);
        QueueTicket::factory()->forService($service)->create();

        $this->actingAs($user)
            ->get(route('queue.overview', $user->currentTeam))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('operations.stats.waiting', 0)
                ->has('operations.services', 0)
                ->has('operations.counters', 0));
    }

    public function test_counter_changes_broadcast_to_previous_and_new_service_queues(): void
    {
        Event::fake([QueueUpdated::class]);
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $previousService = QueueService::factory()->create(['team_id' => $team->id]);
        $newService = QueueService::factory()->create(['team_id' => $team->id]);
        $counter = QueueCounter::factory()->create(['team_id' => $team->id]);
        $counter->services()->attach($previousService);

        app(SaveQueueCounter::class)->handle($team, [
            'location_id' => null,
            'assigned_user_id' => null,
            'service_ids' => [$newService->id],
            'name' => $counter->name,
            'code' => $counter->code,
            'status' => QueueCounterStatus::Paused->value,
        ], $counter);

        Event::assertDispatchedTimes(QueueUpdated::class, 2);
        Event::assertDispatched(
            QueueUpdated::class,
            fn (QueueUpdated $event) => $event->serviceId === $previousService->id,
        );
        Event::assertDispatched(
            QueueUpdated::class,
            fn (QueueUpdated $event) => $event->serviceId === $newService->id,
        );
    }
}
