<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\SaveQueueCounter;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueMultiLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_dashboard_can_scope_a_single_branch(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $dubai = Location::factory()->create(['team_id' => $team->id, 'name' => 'Dubai Branch']);
        $sharjah = Location::factory()->create(['team_id' => $team->id, 'name' => 'Sharjah Branch']);
        $dubaiService = QueueService::factory()->create(['team_id' => $team->id, 'location_id' => $dubai->id, 'name' => 'Dubai Registration']);
        $sharjahService = QueueService::factory()->create(['team_id' => $team->id, 'location_id' => $sharjah->id, 'name' => 'Sharjah Registration']);
        $dubaiCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'location_id' => $dubai->id, 'name' => 'Dubai Counter']);
        $sharjahCounter = QueueCounter::factory()->create(['team_id' => $team->id, 'location_id' => $sharjah->id, 'name' => 'Sharjah Counter']);
        $dubaiCounter->services()->attach($dubaiService);
        $sharjahCounter->services()->attach($sharjahService);
        QueueTicket::factory()->forService($dubaiService)->create(['number' => 'D001']);
        QueueTicket::factory()->forService($sharjahService)->create(['number' => 'S001']);

        $this->actingAs($user)
            ->get(route('queue.overview', ['current_team' => $team, 'location_id' => $dubai->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.location_id', $dubai->id)
                ->where('operations.stats.waiting', 1)
                ->has('operations.locations', 1)
                ->where('operations.locations.0.name', 'Dubai Branch')
                ->has('operations.services', 1)
                ->where('operations.services.0.name', 'Dubai Registration')
                ->has('operations.counters', 1)
                ->where('operations.counters.0.name', 'Dubai Counter')
                ->has('locations', 2));
    }

    public function test_corporate_analytics_compares_locations_and_excludes_other_tenants(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $dubai = Location::factory()->create(['team_id' => $team->id, 'name' => 'Dubai Branch']);
        $abuDhabi = Location::factory()->create(['team_id' => $team->id, 'name' => 'Abu Dhabi Branch']);
        $dubaiService = QueueService::factory()->create(['team_id' => $team->id, 'location_id' => $dubai->id]);
        $abuDhabiService = QueueService::factory()->create(['team_id' => $team->id, 'location_id' => $abuDhabi->id]);

        QueueTicket::factory()->forService($dubaiService)->create([
            'number' => 'D010',
            'status' => QueueTicketStatus::Completed,
            'waiting_duration_seconds' => 120,
            'serving_duration_seconds' => 300,
            'completed_at' => now(),
        ]);
        QueueTicket::factory()->forService($abuDhabiService)->create([
            'number' => 'A010',
            'status' => QueueTicketStatus::NoShow,
            'waiting_duration_seconds' => 600,
            'completed_at' => now(),
        ]);

        $other = User::factory()->create();
        $otherLocation = Location::factory()->create(['team_id' => $other->currentTeam->id, 'name' => 'Foreign Branch']);
        $otherService = QueueService::factory()->create(['team_id' => $other->currentTeam->id, 'location_id' => $otherLocation->id]);
        QueueTicket::factory()->forService($otherService)->create(['number' => 'X001']);

        $this->actingAs($user)
            ->get(route('queue.reports', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.tickets', 2)
                ->has('report.locations', 2)
                ->where('report.locations.0.name', 'Dubai Branch')
                ->where('report.locations.0.average_wait_seconds', 120)
                ->where('report.locations.0.sla_compliance', 100)
                ->where('report.locations.1.name', 'Abu Dhabi Branch')
                ->where('report.locations.1.no_shows', 1));
    }

    public function test_counter_cannot_serve_a_service_from_another_location(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $dubai = Location::factory()->create(['team_id' => $team->id, 'name' => 'Dubai Branch']);
        $sharjah = Location::factory()->create(['team_id' => $team->id, 'name' => 'Sharjah Branch']);
        $sharjahService = QueueService::factory()->create([
            'team_id' => $team->id,
            'location_id' => $sharjah->id,
        ]);

        try {
            app(SaveQueueCounter::class)->handle($team, [
                'location_id' => $dubai->id,
                'assigned_user_id' => null,
                'service_ids' => [$sharjahService->id],
                'name' => 'Dubai Counter',
                'code' => 'DXB-01',
                'status' => QueueCounterStatus::Open->value,
            ]);

            $this->fail('Expected cross-location assignment validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_ids', $exception->errors());
        }

        $this->assertDatabaseMissing('queue_counters', [
            'team_id' => $team->id,
            'code' => 'DXB-01',
        ]);
    }
}
