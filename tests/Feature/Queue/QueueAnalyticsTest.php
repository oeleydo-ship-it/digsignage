<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueTicketStatus;
use App\Enums\TeamRole;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_analytics_calculates_volume_durations_rates_and_performance(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(15));
        [$user, $member, $location, $service, $counter] = $this->analyticsFixture();

        $this->ticket($service, $counter, $user, 'A001', QueueTicketStatus::Completed, 60, 120, now()->setTime(9, 0));
        $this->ticket($service, $counter, $user, 'A002', QueueTicketStatus::Completed, 180, 240, now()->setTime(10, 0));
        $this->ticket($service, $counter, $member, 'A003', QueueTicketStatus::NoShow, 300, null, now()->setTime(10, 30));
        $this->ticket($service, $counter, $member, 'A004', QueueTicketStatus::Cancelled, 600, null, now()->setTime(11, 0));

        $this->actingAs($user)
            ->get(route('queue.reports', [
                'current_team' => $user->currentTeam,
                'from' => now()->toDateString(),
                'until' => now()->toDateString(),
                'location_id' => $location->id,
                'service_id' => $service->id,
                'counter_id' => $counter->id,
                'sla_minutes' => 5,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('queue/reports')
                ->where('report.summary.tickets', 4)
                ->where('report.summary.completed', 2)
                ->where('report.summary.abandonment_rate', 25)
                ->where('report.summary.no_show_rate', 25)
                ->where('report.summary.sla_compliance', 75)
                ->where('report.waiting_time.average', 285)
                ->where('report.waiting_time.median', 240)
                ->where('report.waiting_time.maximum', 600)
                ->where('report.waiting_time.minimum', 60)
                ->where('report.service_time.average', 180)
                ->where('report.peak_hours.0.label', '10:00')
                ->where('report.peak_hours.0.value', 2)
                ->where('report.staff.0.tickets_served', 2)
                ->where('report.counters.0.tickets_served', 2)
                ->where('report.services.0.tickets', 4)
                ->has('report.volume.hour', 3)
                ->has('report.volume.day', 1)
                ->has('options.locations', 1)
                ->has('options.services', 1)
                ->has('options.counters', 1));
    }

    public function test_queue_analytics_employee_filter_and_tenant_scope_are_enforced(): void
    {
        [$user, $member, , $service, $counter] = $this->analyticsFixture();
        $this->ticket($service, $counter, $user, 'A010', QueueTicketStatus::Completed, 60, 120, now());
        $this->ticket($service, $counter, $member, 'A011', QueueTicketStatus::NoShow, 90, null, now());
        $other = User::factory()->create();
        $otherService = QueueService::factory()->create(['team_id' => $other->currentTeam->id]);
        QueueTicket::factory()->forService($otherService)->create(['number' => 'X999']);

        $this->actingAs($user)
            ->get(route('queue.reports', [
                'current_team' => $user->currentTeam,
                'employee_id' => $member->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.summary.tickets', 1)
                ->where('report.summary.no_show_rate', 100)
                ->where('report.staff.0.id', $member->id));
    }

    public function test_filtered_queue_analytics_can_be_exported_as_csv(): void
    {
        [$user, , , $service, $counter] = $this->analyticsFixture();
        $this->ticket($service, $counter, $user, 'A020', QueueTicketStatus::Completed, 75, 180, now());

        $response = $this->actingAs($user)->get(route('queue.reports.export', [
            'current_team' => $user->currentTeam,
            'service_id' => $service->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('ticket,issued_at,status,location,service,counter,employee,waiting_seconds,service_seconds', $content);
        $this->assertStringContainsString('A020', $content);
        $this->assertStringContainsString('Registration', $content);
    }

    /** @return array{User, User, Location, QueueService, QueueCounter} */
    protected function analyticsFixture(): array
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $location = Location::factory()->create(['team_id' => $team->id, 'name' => 'Main Branch']);
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Registration',
        ]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'location_id' => $location->id,
            'name' => 'Counter One',
        ]);
        $counter->services()->attach($service);

        return [$user, $member, $location, $service, $counter];
    }

    protected function ticket(
        QueueService $service,
        QueueCounter $counter,
        User $employee,
        string $number,
        QueueTicketStatus $status,
        int $waitingSeconds,
        ?int $servingSeconds,
        mixed $createdAt,
    ): QueueTicket {
        return QueueTicket::factory()->forService($service)->create([
            'number' => $number,
            'status' => $status,
            'counter_id' => $counter->id,
            'assigned_user_id' => $employee->id,
            'waiting_duration_seconds' => $waitingSeconds,
            'serving_duration_seconds' => $servingSeconds,
            'completed_at' => $status->isClosed() ? $createdAt : null,
            'created_at' => $createdAt,
        ]);
    }
}
