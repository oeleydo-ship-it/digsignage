<?php

namespace App\Http\Requests\Queue;

use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Models\QueueService;
use App\Support\QueueOpeningHours;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueServiceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $service = $this->route('queueService');
        $serviceId = $service instanceof QueueService ? $service->id : null;

        $uniqueCode = Rule::unique('queue_services', 'code')->where('team_id', $teamId);
        $uniquePrefix = Rule::unique('queue_services', 'ticket_prefix')->where('team_id', $teamId);

        if ($serviceId) {
            $uniqueCode->ignore($serviceId);
            $uniquePrefix->ignore($serviceId);
        }

        $hours = [];

        foreach (QueueOpeningHours::weekdays() as $day) {
            $hours["opening_hours.{$day}"] = ['required', 'array'];
            $hours["opening_hours.{$day}.open"] = ['required', 'date_format:H:i'];
            $hours["opening_hours.{$day}.close"] = ['required', 'date_format:H:i'];
            $hours["opening_hours.{$day}.closed"] = ['required', 'boolean'];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', $uniqueCode],
            'ticket_prefix' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/', $uniquePrefix],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'opening_hours' => ['required', 'array'],
            ...$hours,
            'average_service_duration_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'max_queue_capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'numbering_reset' => ['required', Rule::enum(QueueNumberingReset::class)],
            'priority_rules' => ['nullable', 'array'],
            'default_priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'queue_strategy' => ['sometimes', Rule::enum(QueueStrategy::class)],
            'starvation' => ['nullable', 'array'],
            'starvation.max_priority_wait_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'starvation.promote_after_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'display_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $prefix = $this->input('ticket_prefix');

        $merge = [];

        if (is_string($code)) {
            $merge['code'] = strtoupper(trim($code));
        }

        if (is_string($prefix)) {
            $merge['ticket_prefix'] = strtoupper(trim($prefix));
        }

        if ($this->has('opening_hours')) {
            $merge['opening_hours'] = QueueOpeningHours::normalize($this->input('opening_hours'));
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('opening_hours')) {
            foreach (QueueOpeningHours::weekdays() as $day) {
                $closed = $merge['opening_hours'][$day]['closed'] ?? false;
                $merge['opening_hours'][$day]['closed'] = filter_var($closed, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $minutes = $this->input('average_service_duration_minutes');

        if ($this->input('average_service_duration_seconds') === null && is_numeric($minutes)) {
            $merge['average_service_duration_seconds'] = (int) round(((float) $minutes) * 60);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Validation failures use UrlGenerator::previous(), not redirect()->back().
     */
    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
