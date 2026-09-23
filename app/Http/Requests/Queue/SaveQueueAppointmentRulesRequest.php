<?php

namespace App\Http\Requests\Queue;

use App\Support\StayOnPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQueueAppointmentRulesRequest extends FormRequest
{
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'priority_id' => ['nullable', 'integer', Rule::exists('queue_priorities', 'id')->where(fn ($query) => $query->where('team_id', $teamId)->where('is_active', true))],
            'check_in_before_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'check_in_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
