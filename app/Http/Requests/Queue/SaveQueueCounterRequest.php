<?php

namespace App\Http\Requests\Queue;

use App\Enums\QueueCounterStatus;
use App\Models\QueueCounter;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueCounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $counter = $this->route('queueCounter');

        if ($counter instanceof QueueCounter) {
            return $user->can('update', $counter);
        }

        return $user->can('create', QueueCounter::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $counter = $this->route('queueCounter');
        $counterId = $counter instanceof QueueCounter ? $counter->id : null;

        $uniqueCode = Rule::unique('queue_counters', 'code')->where('team_id', $teamId);

        if ($counterId) {
            $uniqueCode->ignore($counterId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', $uniqueCode],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'status' => ['required', Rule::enum(QueueCounterStatus::class)],
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $teamId),
            ],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'integer',
                Rule::exists('queue_services', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $merge = [];

        if (is_string($code)) {
            $merge['code'] = strtoupper(trim($code));
        }

        $serviceIds = $this->input('service_ids');

        if (is_array($serviceIds)) {
            $merge['service_ids'] = array_values(array_unique(array_map('intval', $serviceIds)));
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
