<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\EvaluateQueueAlerts;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Enums\SignageAlert;
use App\Jobs\SendSignageAlert;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_alert_settings_have_safe_defaults_and_can_be_saved(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('queue.settings', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alerts.enabled', false)
                ->where('alerts.average_wait_minutes', 20)
                ->where('alerts.waiting_customers', 30)
                ->where('alerts.customer_wait_minutes', 45)
                ->where('alerts.cooldown_minutes', 30));

        $this->actingAs($user)
            ->patch(route('queue.settings.alerts.update', $team), [
                'enabled' => true,
                'average_wait_minutes' => 15,
                'waiting_customers' => 25,
                'customer_wait_minutes' => 40,
                'no_counter_available' => true,
                'capacity_reached' => false,
                'counter_offline' => true,
                'cooldown_minutes' => 10,
            ])
            ->assertRedirect();

        $alerts = QueueSetting::resolveForTeam($team)->settings['alerts'];
        $this->assertTrue($alerts['enabled']);
        $this->assertSame(15, $alerts['average_wait_minutes']);
        $this->assertSame(25, $alerts['waiting_customers']);
        $this->assertFalse($alerts['capacity_reached']);
        $this->assertSame(10, $alerts['cooldown_minutes']);
    }

    public function test_evaluator_queues_each_active_alert_and_suppresses_repeats(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $settings = QueueSetting::resolveForTeam($team);
        $settings->update(['settings' => array_replace_recursive($settings->settings, [
            'alerts' => [
                'enabled' => true,
                'average_wait_minutes' => 20,
                'waiting_customers' => 1,
                'customer_wait_minutes' => 45,
                'no_counter_available' => true,
                'capacity_reached' => true,
                'counter_offline' => true,
                'cooldown_minutes' => 30,
            ],
        ])]);
        $service = QueueService::factory()->create([
            'team_id' => $team->id,
            'name' => 'Registration',
            'max_queue_capacity' => 2,
        ]);
        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'status' => QueueCounterStatus::Paused,
        ]);
        $counter->services()->attach($service);

        foreach ([1, 2] as $sequence) {
            QueueTicket::factory()->forService($service)->create([
                'number' => $service->ticket_prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'sequence' => $sequence,
                'queue_position' => $sequence,
                'status' => QueueTicketStatus::Waiting,
                'created_at' => now()->subMinutes(61),
            ]);
        }

        $this->assertSame(6, app(EvaluateQueueAlerts::class)->handle($team));
        $this->assertSame(0, app(EvaluateQueueAlerts::class)->handle($team));

        foreach ([
            SignageAlert::QueueAverageWaitHigh,
            SignageAlert::QueueWaitingCountHigh,
            SignageAlert::QueueCustomerWaitHigh,
            SignageAlert::QueueNoCounterAvailable,
            SignageAlert::QueueCapacityReached,
            SignageAlert::QueueCounterOffline,
        ] as $event) {
            Queue::assertPushed(SendSignageAlert::class, fn (SendSignageAlert $job): bool => $job->teamId === $team->id
                && $job->event === $event->value);
        }

        Queue::assertPushed(SendSignageAlert::class, 6);
    }

    public function test_recovery_rearms_an_alert_and_team_filter_is_respected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $team = $user->currentTeam;
        $otherTeam = $other->currentTeam;

        foreach ([$team, $otherTeam] as $configuredTeam) {
            $settings = QueueSetting::resolveForTeam($configuredTeam);
            $settings->update(['settings' => array_replace_recursive($settings->settings, [
                'alerts' => [
                    'enabled' => true,
                    'average_wait_minutes' => 20,
                    'waiting_customers' => 30,
                    'customer_wait_minutes' => 45,
                    'no_counter_available' => false,
                    'capacity_reached' => false,
                    'counter_offline' => true,
                    'cooldown_minutes' => 30,
                ],
            ])]);
        }

        $counter = QueueCounter::factory()->create([
            'team_id' => $team->id,
            'status' => QueueCounterStatus::Paused,
        ]);
        QueueCounter::factory()->create([
            'team_id' => $otherTeam->id,
            'status' => QueueCounterStatus::Paused,
        ]);

        $this->assertSame(1, app(EvaluateQueueAlerts::class)->handle($team));
        Queue::assertPushed(SendSignageAlert::class, 1);

        $counter->update(['status' => QueueCounterStatus::Open]);
        $this->assertSame(0, app(EvaluateQueueAlerts::class)->handle($team));
        $counter->update(['status' => QueueCounterStatus::Paused]);
        $this->assertSame(1, app(EvaluateQueueAlerts::class)->handle($team));
        Queue::assertPushed(SendSignageAlert::class, 2);
    }
}
