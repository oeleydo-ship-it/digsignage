<?php

namespace App\Actions\Queue;

use App\Data\QueueAppointmentConfig;
use App\Enums\QueueAppointmentCheckInSource;
use App\Enums\QueueAppointmentStatus;
use App\Enums\QueueTicketSource;
use App\Models\QueueAppointment;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use App\Support\QueueTransaction;
use Illuminate\Validation\ValidationException;

class CheckInQueueAppointment
{
    public function __construct(protected IssueQueueTicket $issueQueueTicket) {}

    public function handle(
        QueueAppointment $appointment,
        QueueAppointmentCheckInSource $source,
        ?User $actor = null,
    ): QueueTicket {
        return QueueTransaction::run(function () use ($appointment, $source, $actor) {
            $appointment = QueueAppointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($appointment->status === QueueAppointmentStatus::CheckedIn && $appointment->ticket !== null) {
                return $appointment->ticket;
            }

            if ($appointment->status !== QueueAppointmentStatus::Scheduled) {
                throw ValidationException::withMessages(['reference' => __('This appointment cannot be checked in.')]);
            }

            $settings = QueueSetting::resolveForTeam($appointment->team);
            $config = QueueAppointmentConfig::resolve($settings->settings ?? []);
            $now = now();

            if ($now->isBefore($appointment->scheduled_at->copy()->subMinutes($config->checkInBeforeMinutes))) {
                throw ValidationException::withMessages(['reference' => __('Check-in is not open for this appointment yet.')]);
            }

            if ($now->isAfter($appointment->scheduled_at->copy()->addMinutes($config->checkInAfterMinutes))) {
                throw ValidationException::withMessages(['reference' => __('The check-in window for this appointment has closed.')]);
            }

            $ticket = $this->issueQueueTicket->handle($appointment->team, $appointment->service, [
                'source' => QueueTicketSource::Appointment,
                'customer_name' => $appointment->customer_name,
                'customer_phone' => $appointment->customer_phone,
                'customer_email' => $appointment->customer_email,
                'queue_priority_id' => $config->priorityId,
            ], $actor);

            $appointment->forceFill([
                'queue_ticket_id' => $ticket->id,
                'status' => QueueAppointmentStatus::CheckedIn,
                'check_in_source' => $source,
                'checked_in_at' => $now,
            ])->save();

            return $ticket;
        });
    }
}
