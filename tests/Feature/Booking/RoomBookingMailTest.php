<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomBookingNotice;
use App\Enums\RoomBookingSource;
use App\Enums\TeamRole;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RoomBookingMail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RoomBookingMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
        Notification::fake();
    }

    /**
     * @return array<string, mixed>
     */
    protected function guestPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Client pitch',
            'organizer_name' => 'Dana Guest',
            'organizer_email' => 'dana@example.com',
            'date' => '2026-09-28',
            'start_time' => '10:00',
            'duration' => 60,
        ], $overrides);
    }

    protected function assertMailed(string $email, RoomBookingNotice $notice): void
    {
        Notification::assertSentOnDemand(
            RoomBookingMail::class,
            fn (RoomBookingMail $mail, array $channels, AnonymousNotifiable $notifiable) => $mail->notice === $notice
                && array_key_exists($email, (array) $notifiable->routes['mail']),
        );
    }

    public function test_guests_get_a_confirmation_with_a_calendar_file(): void
    {
        $room = MeetingRoom::factory()->create(['name' => 'Studio']);

        $this->post(route('rooms.public.store', $room->booking_token), $this->guestPayload())->assertSessionHasNoErrors();

        $this->assertMailed('dana@example.com', RoomBookingNotice::Confirmed);

        $booking = RoomBooking::query()->sole();
        $mail = (new RoomBookingMail($booking, RoomBookingNotice::Confirmed))->toMail(new AnonymousNotifiable);

        $this->assertSame('Booking confirmed: Studio', $mail->subject);
        $this->assertCount(1, $mail->rawAttachments);
        $ics = $mail->rawAttachments[0]['data'];
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20260928T100000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20260928T110000Z\r\n", $ics);
        $this->assertStringContainsString('UID:room-booking-'.$booking->id.'@', $ics);
        $this->assertStringContainsString("SUMMARY:Client pitch\r\n", $ics);
    }

    public function test_requests_needing_approval_email_the_guest_and_the_managers(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->create();
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $room = MeetingRoom::factory()->requiresApproval()->create(['team_id' => $team->id]);

        $this->post(route('rooms.public.store', $room->booking_token), $this->guestPayload());

        $this->assertMailed('dana@example.com', RoomBookingNotice::Requested);
        Notification::assertSentTo($admin, RoomBookingMail::class, fn (RoomBookingMail $mail) => $mail->notice === RoomBookingNotice::ApprovalNeeded);
        Notification::assertNotSentTo($member, RoomBookingMail::class);

        $booking = RoomBooking::query()->sole();
        $admin->switchTeam($team);

        $this->actingAs($admin)->post(route('bookings.approve', [$team, $booking]))->assertSessionHasNoErrors();
        $this->assertMailed('dana@example.com', RoomBookingNotice::Confirmed);
    }

    public function test_declines_cancellations_and_changes_are_emailed(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->requiresApproval()->create(['team_id' => $team->id]);
        $request = RoomBooking::factory()->for($room, 'room')->pending()->create(['organizer_email' => 'decline@example.com']);
        $booking = RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 14:00'),
            CarbonImmutable::parse('2026-09-28 15:00'),
        )->create(['organizer_email' => 'host@example.com']);

        $this->actingAs($owner)->post(route('bookings.decline', [$team, $request]));
        $this->assertMailed('decline@example.com', RoomBookingNotice::Declined);

        $this->actingAs($owner)->patch(route('bookings.update', [$team, $booking]), [
            'meeting_room_id' => $room->id,
            'title' => 'Moved meeting',
            'date' => '2026-09-28',
            'start_time' => '16:00',
            'end_time' => '17:00',
            'organizer_email' => 'host@example.com',
        ])->assertSessionHasNoErrors();
        $this->assertMailed('host@example.com', RoomBookingNotice::Rescheduled);

        $this->actingAs($owner)->post(route('bookings.cancel', [$team, $booking]));
        $this->assertMailed('host@example.com', RoomBookingNotice::Cancelled);

        $cancel = (new RoomBookingMail($booking->refresh(), RoomBookingNotice::Cancelled))->toMail(new AnonymousNotifiable);
        $this->assertStringContainsString("METHOD:CANCEL\r\n", $cancel->rawAttachments[0]['data']);
        $this->assertStringContainsString("STATUS:CANCELLED\r\n", $cancel->rawAttachments[0]['data']);
    }

    public function test_nothing_is_sent_without_an_email_or_for_outlook_meetings(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $outlook = RoomBooking::factory()->for($room, 'room')->create([
            'source' => RoomBookingSource::Microsoft365,
            'organizer_email' => 'someone@contoso.com',
        ]);
        $noEmail = RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 18:00'),
            CarbonImmutable::parse('2026-09-28 19:00'),
        )->create(['organizer_email' => null]);

        $this->actingAs($owner)->post(route('bookings.cancel', [$team, $outlook]));
        $this->actingAs($owner)->post(route('bookings.cancel', [$team, $noEmail]));

        Notification::assertNothingSent();
    }

    public function test_calendar_text_is_escaped_and_long_lines_are_folded(): void
    {
        $room = MeetingRoom::factory()->create(['name' => 'Room; A, B']);
        $booking = RoomBooking::factory()->for($room, 'room')->create([
            'title' => str_repeat('Quarterly planning, budgets; and roadmap ', 4),
            'notes' => "Line one\nLine two",
        ]);

        $ics = (new RoomBookingMail($booking, RoomBookingNotice::Confirmed))->calendarFile($booking, 'UTC');

        $this->assertStringContainsString('LOCATION:Room\; A\, B', $ics);
        $this->assertStringContainsString('Line one\nLine two', $ics);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }
}
