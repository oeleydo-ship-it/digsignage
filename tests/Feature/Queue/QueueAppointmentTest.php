<?php

namespace Tests\Feature\Queue;

use App\Enums\QueueAppointmentCheckInSource;
use App\Enums\QueueAppointmentStatus;
use App\Enums\QueueTicketSource;
use App\Enums\TeamRole;
use App\Models\QueueAppointment;
use App\Models\QueueKiosk;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_view_an_appointment(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $service = QueueService::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)->post(route('queue.appointments.store', $team), [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'queue_service_id' => $service->id,
            'scheduled_at' => now()->addHour()->toDateTimeString(),
            'reference' => 'APPT-1001',
        ])->assertRedirect();

        $this->assertDatabaseHas('queue_appointments', [
            'team_id' => $team->id,
            'customer_name' => 'Ada Lovelace',
            'reference' => 'APPT-1001',
            'status' => QueueAppointmentStatus::Scheduled->value,
        ]);

        $this->actingAs($user)->get(route('queue.appointments', $team))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('queue/appointments')
                ->where('appointments.data.0.reference', 'APPT-1001'));
    }

    public function test_staff_check_in_issues_one_prioritized_appointment_ticket(): void
    {
        [$user, $service, $appointment] = $this->appointmentAt(now());
        $team = $user->currentTeam;
        QueueSetting::resolveForTeam($team);
        $priority = QueuePriority::query()->where('team_id', $team->id)->where('code', 'vip')->firstOrFail();
        $settings = QueueSetting::resolveForTeam($team);
        $settings->forceFill(['settings' => [
            ...$settings->settings,
            'appointments' => ['priority_id' => $priority->id, 'check_in_before_minutes' => 30, 'check_in_after_minutes' => 15],
        ]])->save();

        $url = route('queue.appointments.check-in', [$team, $appointment]);
        $this->actingAs($user)->post($url)->assertRedirect();
        $this->actingAs($user)->post($url)->assertRedirect();

        $appointment->refresh();
        $this->assertSame(QueueAppointmentStatus::CheckedIn, $appointment->status);
        $this->assertSame(QueueAppointmentCheckInSource::Staff, $appointment->check_in_source);
        $this->assertDatabaseCount('queue_tickets', 1);
        $ticket = QueueTicket::firstOrFail();
        $this->assertSame(QueueTicketSource::Appointment, $ticket->source);
        $this->assertSame($priority->id, $ticket->queue_priority_id);
        $this->assertSame($service->id, $ticket->queue_service_id);
    }

    public function test_check_in_window_is_enforced(): void
    {
        [$user, , $appointment] = $this->appointmentAt(now()->addHours(2));

        $this->actingAs($user)
            ->post(route('queue.appointments.check-in', [$user->currentTeam, $appointment]))
            ->assertSessionHasErrors('reference');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_qr_and_reference_pages_check_in_without_authentication(): void
    {
        [, , $qrAppointment] = $this->appointmentAt(now());
        [, , $referenceAppointment] = $this->appointmentAt(now(), 'REF-CHECKIN');

        $this->get(route('queue.appointment.check-in.show', [$qrAppointment, 'source' => 'qr']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('queue/appointment-check-in')->where('source', 'qr'));
        $this->post(route('queue.appointment.check-in.store', [$qrAppointment]), ['source' => 'qr'])->assertRedirect();
        $this->post(route('queue.appointment.check-in.store', [$referenceAppointment]))->assertRedirect();

        $this->assertSame(QueueAppointmentCheckInSource::Qr, $qrAppointment->refresh()->check_in_source);
        $this->assertSame(QueueAppointmentCheckInSource::Reference, $referenceAppointment->refresh()->check_in_source);
    }

    public function test_kiosk_reference_check_in_issues_the_appointment_ticket(): void
    {
        [, , $appointment] = $this->appointmentAt(now(), 'KIOSK-123');
        $kiosk = QueueKiosk::factory()->create(['team_id' => $appointment->team_id, 'location_id' => null]);

        $this->post(route('queue.kiosk.appointments.check-in', $kiosk), ['reference' => 'kiosk-123'])
            ->assertRedirect();

        $this->assertSame(QueueAppointmentCheckInSource::Kiosk, $appointment->refresh()->check_in_source);
        $this->assertNotNull($appointment->queue_ticket_id);
    }

    public function test_owner_can_save_rules_and_read_only_member_cannot_manage_appointments(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        QueueSetting::resolveForTeam($team);
        $priority = QueuePriority::query()->where('team_id', $team->id)->where('code', 'vip')->firstOrFail();

        $this->actingAs($owner)->patch(route('queue.appointments.rules.update', $team), [
            'priority_id' => $priority->id,
            'check_in_before_minutes' => 45,
            'check_in_after_minutes' => 20,
        ])->assertRedirect();

        $this->assertSame(45, QueueSetting::resolveForTeam($team)->settings['appointments']['check_in_before_minutes']);

        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);
        $service = QueueService::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)->post(route('queue.appointments.store', $team), [
            'customer_name' => 'Blocked User',
            'queue_service_id' => $service->id,
            'scheduled_at' => now()->toDateTimeString(),
        ])->assertForbidden();
    }

    /** @return array{User, QueueService, QueueAppointment} */
    protected function appointmentAt($scheduledAt, ?string $reference = null): array
    {
        $user = User::factory()->create();
        $service = QueueService::factory()->create(['team_id' => $user->currentTeam->id]);
        $appointment = QueueAppointment::factory()->create([
            'team_id' => $user->currentTeam->id,
            'queue_service_id' => $service->id,
            'scheduled_at' => $scheduledAt,
            'reference' => $reference ?? fake()->unique()->bothify('APPT-####'),
        ]);

        return [$user, $service, $appointment];
    }
}
