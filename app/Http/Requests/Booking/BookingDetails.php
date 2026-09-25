<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Typed meeting details shared by the staff and public booking forms.
 */
final class BookingDetails
{
    /**
     * @return array{title: string, organizer_name: string|null, organizer_email: string|null, attendees: int|null, notes: string|null}
     */
    public static function from(FormRequest $request): array
    {
        $text = function (string $key) use ($request): ?string {
            $value = $request->validated($key);

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        $attendees = $request->validated('attendees');

        return [
            'title' => (string) $text('title'),
            'organizer_name' => $text('organizer_name'),
            'organizer_email' => $text('organizer_email'),
            'attendees' => is_numeric($attendees) ? (int) $attendees : null,
            'notes' => $text('notes'),
        ];
    }
}
