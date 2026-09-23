<?php

namespace Tests\Feature\Queue;

use App\Actions\Queue\DispatchQueueCustomerNotification;
use App\Actions\Queue\EvaluateApproachingQueueAppointments;
use App\Enums\QueueAppointmentStatus;
use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Enums\TeamRole;
use App\Jobs\SendQueueCustomerNotification;
use App\Models\QueueAppointment;
use App\Models\QueueNotificationDelivery;
use App\Models\QueueNotificationRule;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueNotifications\QueueNotificationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueCustomerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_rules_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('queue.settings.customer-notifications.update', $user->currentTeam), [
            'rules' => [
                'ticket_created' => ['email' => true, 'sms' => true],
                'appointment_approaching' => ['whatsapp' => true],
            ],
            'appointment_minutes_before' => 90,
        ])->assertRedirect();

        $this->assertDatabaseCount('queue_notification_rules', 28);
        $this->assertDatabaseHas('queue_notification_rules', [
            'team_id' => $user->currentTeam->id,
            'event' => 'ticket_created',
            'channel' => 'email',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('queue_notification_rules', [
            'event' => 'appointment_approaching',
            'channel' => 'whatsapp',
            'minutes_before' => 90,
        ]);
    }

    public function test_read_only_member_cannot_save_notification_rules(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $owner->currentTeam;
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)->patch(route('queue.settings.customer-notifications.update', $team), [
            'rules' => ['ticket_created' => ['email' => true]],
            'appointment_minutes_before' => 60,
        ])->assertForbidden();

        $this->assertDatabaseCount('queue_notification_rules', 0);
    }

    public function test_ticket_notification_is_queued_once_and_missing_destinations_are_skipped(): void
    {
        Queue::fake();
        [$ticket, $teamId] = $this->ticket();
        $ticket->forceFill(['customer_email' => 'customer@example.com', 'customer_phone' => null])->saveQuietly();
        $this->rule($teamId, QueueNotificationEvent::TicketCreated, QueueNotificationChannel::Email);
        $this->rule($teamId, QueueNotificationEvent::TicketCreated, QueueNotificationChannel::Sms);

        $dispatch = app(DispatchQueueCustomerNotification::class);
        $dispatch->forTicket($ticket, QueueNotificationEvent::TicketCreated, 'created');
        $dispatch->forTicket($ticket, QueueNotificationEvent::TicketCreated, 'created');

        $this->assertDatabaseCount('queue_notification_deliveries', 2);
        $this->assertDatabaseHas('queue_notification_deliveries', ['channel' => 'email', 'status' => 'pending']);
        $this->assertDatabaseHas('queue_notification_deliveries', ['channel' => 'sms', 'status' => 'skipped']);
        Queue::assertPushed(SendQueueCustomerNotification::class, 1);
    }

    public function test_sms_provider_delivery_runs_in_the_queue_job(): void
    {
        Queue::fake();
        Http::fake(['https://sms.example.test/*' => Http::response(['ok' => true])]);
        config(['queue-notifications.providers.sms' => 'https://sms.example.test/send']);
        [$ticket, $teamId] = $this->ticket();
        $ticket->forceFill(['customer_phone' => '+971500000000'])->saveQuietly();
        $this->rule($teamId, QueueNotificationEvent::TicketCalled, QueueNotificationChannel::Sms);
        app(DispatchQueueCustomerNotification::class)->forTicket($ticket, QueueNotificationEvent::TicketCalled, 'call-1');
        $delivery = QueueNotificationDelivery::firstOrFail();

        (new SendQueueCustomerNotification($delivery->id))->handle(app(QueueNotificationProviderRegistry::class));
        (new SendQueueCustomerNotification($delivery->id))->handle(app(QueueNotificationProviderRegistry::class));

        $this->assertSame('sent', $delivery->refresh()->status);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/send'
            && $request['destination'] === '+971500000000'
            && $request['channel'] === 'sms');
        Http::assertSentCount(1);
    }

    public function test_approaching_appointment_is_queued_once(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $service = QueueService::factory()->create(['team_id' => $user->currentTeam->id]);
        $appointment = QueueAppointment::factory()->create([
            'team_id' => $user->currentTeam->id,
            'queue_service_id' => $service->id,
            'customer_email' => 'appointment@example.com',
            'scheduled_at' => now()->addMinutes(30),
            'status' => QueueAppointmentStatus::Scheduled,
        ]);
        $this->rule($user->currentTeam->id, QueueNotificationEvent::AppointmentApproaching, QueueNotificationChannel::Email, 60);

        $evaluator = app(EvaluateApproachingQueueAppointments::class);
        $this->assertSame(1, $evaluator->handle());
        $this->assertSame(1, $evaluator->handle());

        $this->assertDatabaseCount('queue_notification_deliveries', 1);
        $this->assertDatabaseHas('queue_notification_deliveries', ['queue_appointment_id' => $appointment->id]);
        Queue::assertPushed(SendQueueCustomerNotification::class, 1);
    }

    /** @return array{QueueTicket, int} */
    protected function ticket(): array
    {
        $user = User::factory()->create();
        $service = QueueService::factory()->create(['team_id' => $user->currentTeam->id]);
        $ticket = QueueTicket::factory()->forService($service)->create();

        return [$ticket, $user->currentTeam->id];
    }

    protected function rule(int $teamId, QueueNotificationEvent $event, QueueNotificationChannel $channel, ?int $minutes = null): QueueNotificationRule
    {
        return QueueNotificationRule::query()->create([
            'team_id' => $teamId,
            'event' => $event,
            'channel' => $channel,
            'is_enabled' => true,
            'minutes_before' => $minutes,
        ]);
    }
}
