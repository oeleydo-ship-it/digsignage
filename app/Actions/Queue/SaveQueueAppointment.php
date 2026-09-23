<?php

namespace App\Actions\Queue;

use App\Enums\QueueAppointmentStatus;
use App\Models\Location;
use App\Models\QueueAppointment;
use App\Models\QueueService;
use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveQueueAppointment
{
    /** @param array<string, mixed> $attributes */
    public function handle(Team $team, array $attributes, ?QueueAppointment $appointment = null): QueueAppointment
    {
        $service = QueueService::query()->forTeam($team)->whereKey($attributes['queue_service_id'])->firstOrFail();
        $locationId = filled($attributes['location_id'] ?? null) ? (int) $attributes['location_id'] : $service->location_id;

        if ($service->location_id !== null && $locationId !== $service->location_id) {
            throw ValidationException::withMessages(['location_id' => __('The location must match the selected service.')]);
        }

        if ($locationId !== null) {
            Location::query()->forTeam($team)->whereKey($locationId)->firstOrFail();
        }

        $appointment ??= new QueueAppointment(['team_id' => $team->id]);
        abort_unless($appointment->team_id === $team->id, 403);

        if ($appointment->queue_ticket_id !== null && $appointment->queue_service_id !== $service->id) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('The service cannot be changed after this appointment has checked in.'),
            ]);
        }

        $reference = filled($attributes['reference'] ?? null)
            ? Str::upper((string) $attributes['reference'])
            : $this->reference($team);

        $appointment->fill([
            'queue_service_id' => $service->id,
            'location_id' => $locationId,
            'customer_name' => $attributes['customer_name'],
            'customer_phone' => $attributes['customer_phone'] ?? null,
            'customer_email' => $attributes['customer_email'] ?? null,
            'scheduled_at' => $attributes['scheduled_at'],
            'reference' => $reference,
            'status' => $attributes['status'] ?? $appointment->status ?? QueueAppointmentStatus::Scheduled,
        ])->save();

        return $appointment->refresh();
    }

    protected function reference(Team $team): string
    {
        do {
            $reference = Str::upper(Str::random(10));
        } while (QueueAppointment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
