<?php

namespace App\Http\Requests\Queue;

use App\Enums\QueueAppointmentStatus;
use App\Models\QueueAppointment;
use App\Support\StayOnPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueAppointmentRequest extends FormRequest
{
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $appointment = $this->route('queueAppointment');

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'queue_service_id' => ['required', 'integer', Rule::exists('queue_services', 'id')->where('team_id', $teamId)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('team_id', $teamId)],
            'scheduled_at' => ['required', 'date'],
            'reference' => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('queue_appointments', 'reference')->ignore($appointment instanceof QueueAppointment ? $appointment->id : null),
            ],
            'status' => ['nullable', Rule::enum(QueueAppointmentStatus::class)],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
