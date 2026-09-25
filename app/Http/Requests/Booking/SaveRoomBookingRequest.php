<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomBookingRequest extends FormRequest
{
    /**
     * Dates and times arrive in the room's local time; the controller
     * converts them to UTC using the room's timezone.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'meeting_room_id' => ['required', 'integer', Rule::exists('meeting_rooms', 'id')->where('team_id', $teamId)],
            'title' => ['required', 'string', 'max:160'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'organizer_name' => ['nullable', 'string', 'max:120'],
            'organizer_email' => ['nullable', 'email', 'max:255'],
            'attendees' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after' => __('The meeting must end after it starts.'),
        ];
    }
}
