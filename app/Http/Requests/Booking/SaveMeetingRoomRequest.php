<?php

namespace App\Http\Requests\Booking;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMeetingRoomRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('team_id', $teamId)],
            'description' => ['nullable', 'string', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => ['string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
            'public_booking_enabled' => ['boolean'],
            'requires_approval' => ['boolean'],
            'min_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'max_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'external_calendar_id' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ((int) $this->input('max_duration_minutes') < (int) $this->input('min_duration_minutes')) {
                    $validator->errors()->add('max_duration_minutes', __('The longest booking must be at least the shortest.'));
                }

                if ((string) $this->input('closes_at') !== '' && (string) $this->input('closes_at') <= (string) $this->input('opens_at')) {
                    $validator->errors()->add('closes_at', __('Closing time must be after opening time.'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $amenities = $this->input('amenities');

        if (is_string($amenities)) {
            $amenities = array_values(array_filter(array_map('trim', explode(',', $amenities))));
        }

        $this->merge([
            'amenities' => $amenities ?: [],
            'is_active' => $this->boolean('is_active', true),
            'public_booking_enabled' => $this->boolean('public_booking_enabled'),
            'requires_approval' => $this->boolean('requires_approval'),
            'location_id' => $this->input('location_id') ?: null,
            'external_calendar_id' => $this->input('external_calendar_id') ?: null,
        ]);
    }
}
