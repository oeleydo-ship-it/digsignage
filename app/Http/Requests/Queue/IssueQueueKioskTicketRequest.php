<?php

namespace App\Http\Requests\Queue;

use App\Models\QueueKiosk;
use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueQueueKioskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kiosk = $this->route('queueKiosk');

        return $kiosk instanceof QueueKiosk && $kiosk->is_active;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kiosk = $this->route('queueKiosk');
        $teamId = $kiosk instanceof QueueKiosk ? $kiosk->team_id : 0;

        $service = Rule::exists('queue_services', 'id')
            ->where('team_id', $teamId)
            ->where('is_active', true);

        if ($kiosk instanceof QueueKiosk && $kiosk->location_id !== null) {
            $service->where('location_id', $kiosk->location_id);
        }

        return [
            'queue_service_id' => ['required', 'integer', $service],
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
