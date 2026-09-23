<?php

namespace App\Http\Requests\Queue;

use App\Enums\QueueTicketSource;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueQueueTicketRequest extends FormRequest
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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'source' => ['sometimes', Rule::enum(QueueTicketSource::class)],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'queue_priority_id' => [
                'nullable',
                'integer',
                Rule::exists('queue_priorities', 'id')->where('team_id', $teamId)->where('is_active', true),
            ],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validation failures use UrlGenerator::previous(), not redirect()->back().
     */
    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
