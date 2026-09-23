<?php

namespace App\Http\Requests\Queue;

use App\Models\QueuePriority;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueuePriorityRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $priority = $this->route('queuePriority');
        $priorityId = $priority instanceof QueuePriority ? $priority->id : null;

        $uniqueName = Rule::unique('queue_priorities', 'name')->where('team_id', $teamId);
        $uniqueCode = Rule::unique('queue_priorities', 'code')->where('team_id', $teamId);

        if ($priorityId) {
            $uniqueName->ignore($priorityId);
            $uniqueCode->ignore($priorityId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $uniqueCode],
            'weight' => ['required', 'integer', 'min:0', 'max:10000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $code = $this->input('code');

        if (is_string($code) && $code !== '') {
            $merge['code'] = strtolower(trim($code));
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
