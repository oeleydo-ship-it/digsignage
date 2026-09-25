<?php

namespace App\Notifications;

use App\Enums\RoomBookingNotice;
use App\Models\RoomBooking;
use App\Support\RoomAvailability;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a booking's organizer (or, for approval requests, a manager) about
 * a change to a room booking. Confirmations carry an .ics file so the meeting
 * lands in the recipient's calendar; cancellations remove it again.
 */
class RoomBookingMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public RoomBooking $booking,
        public RoomBookingNotice $notice,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['room.location.parent', 'team']);
        $room = $booking->room;
        $timezone = RoomAvailability::timezone($room);
        $start = $booking->starts_at->setTimezone($timezone);
        $end = $booking->ends_at->setTimezone($timezone);
        $when = $start->isoFormat('dddd D MMMM YYYY').', '.$start->format('H:i').'–'.$end->format('H:i').' ('.$timezone.')';
        $organization = $booking->team->name;

        $message = (new MailMessage)->subject($this->subject($room->name));

        match ($this->notice) {
            RoomBookingNotice::Confirmed => $message
                ->greeting(__('You’re booked'))
                ->line(__(':room is reserved for you.', ['room' => $room->name])),
            RoomBookingNotice::Requested => $message
                ->greeting(__('Request received'))
                ->line(__('Your request for :room is waiting for approval. We will email you as soon as it is confirmed.', ['room' => $room->name])),
            RoomBookingNotice::ApprovalNeeded => $message
                ->greeting(__('A booking needs approval'))
                ->line(__(':name asked to book :room.', ['name' => $booking->organizer_name ?? __('A guest'), 'room' => $room->name])),
            RoomBookingNotice::Rescheduled => $message
                ->greeting(__('Your booking changed'))
                ->line(__('The details of your booking for :room were updated.', ['room' => $room->name])),
            RoomBookingNotice::Declined => $message
                ->greeting(__('Request declined'))
                ->line(__('Sorry, your request for :room could not be approved. Please choose another time.', ['room' => $room->name])),
            RoomBookingNotice::Cancelled => $message
                ->greeting(__('Booking cancelled'))
                ->line(__('Your booking for :room has been cancelled and the room released.', ['room' => $room->name])),
        };

        $message
            ->line('**'.__('Meeting').':** '.$booking->title)
            ->line('**'.__('When').':** '.$when)
            ->line('**'.__('Where').':** '.$room->name.($room->location ? ', '.$room->location->name : ''));

        if ($booking->attendees) {
            $message->line('**'.__('People').':** '.$booking->attendees);
        }

        if ($this->notice === RoomBookingNotice::ApprovalNeeded) {
            $message->action(__('Review request'), route('bookings.index', [
                'current_team' => $booking->team->slug,
                'date' => $start->toDateString(),
            ]));
        } elseif (in_array($this->notice, [RoomBookingNotice::Declined, RoomBookingNotice::Cancelled], true) && $room->publicBookingUrl()) {
            $message->action(__('Book another time'), (string) $room->publicBookingUrl());
        }

        if ($this->notice->hasCalendarFile()) {
            $message->attachData(
                $this->calendarFile($booking, $timezone),
                'meeting.ics',
                ['mime' => 'text/calendar; charset=UTF-8; method='.$this->calendarMethod()],
            );
        }

        return $message->salutation(__('— :organization', ['organization' => $organization]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'room_booking_id' => $this->booking->id,
            'notice' => $this->notice->value,
        ];
    }

    protected function subject(string $room): string
    {
        return match ($this->notice) {
            RoomBookingNotice::Confirmed => __('Booking confirmed: :room', ['room' => $room]),
            RoomBookingNotice::Requested => __('Booking request received: :room', ['room' => $room]),
            RoomBookingNotice::ApprovalNeeded => __('Approval needed: :room', ['room' => $room]),
            RoomBookingNotice::Rescheduled => __('Booking updated: :room', ['room' => $room]),
            RoomBookingNotice::Declined => __('Booking request declined: :room', ['room' => $room]),
            RoomBookingNotice::Cancelled => __('Booking cancelled: :room', ['room' => $room]),
        };
    }

    protected function calendarMethod(): string
    {
        return $this->notice === RoomBookingNotice::Cancelled ? 'CANCEL' : 'PUBLISH';
    }

    /**
     * RFC 5545 event. The UID stays the same for the life of the booking and
     * SEQUENCE grows with every change, so calendar apps update or remove the
     * entry they already have instead of adding a second one.
     */
    public function calendarFile(RoomBooking $booking, string $timezone): string
    {
        $room = $booking->room;
        $format = fn (DateTimeInterface $time) => CarbonImmutable::instance($time)->utc()->format('Ymd\THis\Z');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'digsignage.local';
        $location = $room->name.($room->location ? ', '.$room->location->name : '');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DigSignage//Room booking//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$this->calendarMethod(),
            'BEGIN:VEVENT',
            'UID:room-booking-'.$booking->id.'@'.$host,
            'SEQUENCE:'.($booking->updated_at?->getTimestamp() ?? 0),
            'DTSTAMP:'.$format(CarbonImmutable::now()),
            'DTSTART:'.$format($booking->starts_at),
            'DTEND:'.$format($booking->ends_at),
            'SUMMARY:'.$this->escape($booking->title),
            'LOCATION:'.$this->escape($location),
            'DESCRIPTION:'.$this->escape(trim(($booking->notes ?? '')."\n".__('Times shown in :timezone.', ['timezone' => $timezone]))),
            'STATUS:'.($this->notice === RoomBookingNotice::Cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(fn (string $line) => $this->fold($line), $lines))."\r\n";
    }

    protected function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Lines longer than 75 octets must be folded (RFC 5545, section 3.1).
     */
    protected function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];

        while (strlen($line) > 75) {
            $chunk = mb_strcut($line, 0, 75, 'UTF-8');
            $parts[] = $chunk;
            $line = ' '.substr($line, strlen($chunk));
        }

        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
