<?php

namespace App\Http\Requests\Queue;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinVirtualQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('team') instanceof Team;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;

        return [
            'queue_service_id' => [
                'required',
                'integer',
                Rule::exists('queue_services', 'id')
                    ->where('team_id', $teamId)
                    ->where('is_active', true),
            ],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('team_id', $teamId),
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
