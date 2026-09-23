<?php

namespace App\Http\Requests\Queue;

use App\Support\StayOnPage;
use Illuminate\Foundation\Http\FormRequest;

class SaveQueueNotificationRulesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array'],
            'rules.*' => ['array'],
            'rules.*.*' => ['boolean'],
            'appointment_minutes_before' => ['required', 'integer', 'min:1', 'max:10080'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
