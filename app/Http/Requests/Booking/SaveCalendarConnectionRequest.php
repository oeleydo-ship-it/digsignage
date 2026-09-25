<?php

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveCalendarConnectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A directory (tenant) ID or a verified domain such as contoso.onmicrosoft.com.
            'tenant_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'client_id' => ['required', 'uuid'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $existing = $this->user()?->currentTeam?->calendarConnections()->first();

                if (blank($this->input('client_secret')) && blank($existing?->client_secret)) {
                    $validator->errors()->add('client_secret', __('Enter the client secret from your app registration.'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
