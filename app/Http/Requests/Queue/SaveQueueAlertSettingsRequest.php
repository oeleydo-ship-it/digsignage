<?php

namespace App\Http\Requests\Queue;

use App\Support\StayOnPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveQueueAlertSettingsRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'average_wait_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'waiting_customers' => ['required', 'integer', 'min:1', 'max:100000'],
            'customer_wait_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'no_counter_available' => ['required', 'boolean'],
            'capacity_reached' => ['required', 'boolean'],
            'counter_offline' => ['required', 'boolean'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StayOnPage::url($this);
    }
}
