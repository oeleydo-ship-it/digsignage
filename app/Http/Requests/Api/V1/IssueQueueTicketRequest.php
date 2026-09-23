<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueQueueTicketRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'queue_priority_id' => [
                'nullable',
                'integer',
                Rule::exists('queue_priorities', 'id')->where('team_id', $teamId)->where('is_active', true),
            ],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
