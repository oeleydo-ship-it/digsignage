<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferQueueTicketRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'queue_service_id' => [
                'required',
                'integer',
                Rule::exists('queue_services', 'id')->where('team_id', $teamId)->where('is_active', true),
            ],
            'counter_id' => [
                'nullable',
                'integer',
                Rule::exists('queue_counters', 'id')->where('team_id', $teamId),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
