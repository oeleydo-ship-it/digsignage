<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicRoomBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Client pitch',
            'organizer_name' => 'Dana Guest',
            'organizer_email' => 'dana@example.com',
            'date' => '2026-09-28',
            'start_time' => '10:00',
            'duration' => 60,
            'attendees' => 3,
            'notes' => 'Needs HDMI',
        ], $overrides);
    }

    public function test_guests_see_the_room_and_only_busy_times(): void
    {
        $room = MeetingRoom::factory()->create(['name' => 'Studio', 'opens_at' => '08:00', 'closes_at' => '18:00']);
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 09:00'),
            CarbonImmutable::parse('2026-09-28 09:30'),
        )->create(['title' => 'Secret board meeting', 'organizer_name' => 'CEO']);

        $this->get(route('rooms.public.show', $room->booking_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/public-book')
                ->where('room.name', 'Studio')
                ->where('busy.0', ['start' => 540, 'end' => 570, 'pending' => false])
                ->missing('busy.0.title'))
            ->assertDontSee('Secret board meeting');
    }

    public function test_meetings_crossing_midnight_block_only_their_part_of_the_day(): void
    {
        $room = MeetingRoom::factory()->create();
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-27 23:30'),
            CarbonImmutable::parse('2026-09-28 01:00'),
        )->create();
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 23:00'),
            CarbonImmutable::parse('2026-09-29 02:00'),
        )->create();

        $this->get(route('rooms.public.show', $room->booking_token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('busy.0', ['start' => 0, 'end' => 60, 'pending' => false])
                ->where('busy.1', ['start' => 1380, 'end' => 1440, 'pending' => false]));
    }

    public function test_a_guest_can_book_a_free_slot(): void
    {
        $room = MeetingRoom::factory()->create();

        $this->post(route('rooms.public.store', $room->booking_token), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rooms.public.show', ['meetingRoom' => $room->booking_token, 'date' => '2026-09-28']))
            ->assertSessionHas('room_booking_confirmation.pending', false);

        $booking = RoomBooking::query()->sole();
        $this->assertSame(RoomBookingSource::PublicForm, $booking->source);
        $this->assertSame(RoomBookingStatus::Confirmed, $booking->status);
        $this->assertSame('dana@example.com', $booking->organizer_email);
        $this->assertSame('2026-09-28 11:00:00', $booking->ends_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_rooms_needing_approval_create_pending_requests(): void
    {
        $room = MeetingRoom::factory()->requiresApproval()->create();

        $this->post(route('rooms.public.store', $room->booking_token), $this->payload())
            ->assertSessionHas('room_booking_confirmation.pending', true);

        $this->assertSame(RoomBookingStatus::Pending, RoomBooking::query()->sole()->status);

        // A pending request still holds the slot.
        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['organizer_email' => 'other@example.com']))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_guests_must_follow_the_room_rules(): void
    {
        $room = MeetingRoom::factory()->create([
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 90,
            'capacity' => 4,
        ]);

        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['start_time' => '17:30']))
            ->assertSessionHasErrors('starts_at');
        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['duration' => 15]))
            ->assertSessionHasErrors('ends_at');
        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['attendees' => 9]))
            ->assertSessionHasErrors('attendees');
        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['date' => '2026-12-31']))
            ->assertSessionHasErrors('date');

        $this->assertDatabaseCount('room_bookings', 0);
    }

    public function test_the_honeypot_blocks_bots(): void
    {
        $room = MeetingRoom::factory()->create();

        $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['website' => 'http://spam.test']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('room_bookings', 0);
    }

    public function test_private_or_inactive_rooms_have_no_public_form(): void
    {
        $private = MeetingRoom::factory()->privateOnly()->create();
        $inactive = MeetingRoom::factory()->create(['is_active' => false]);

        $this->get(route('rooms.public.show', $private->booking_token))->assertNotFound();
        $this->post(route('rooms.public.store', $inactive->booking_token), $this->payload())->assertNotFound();
        $this->get('/book/not-a-real-token')->assertNotFound();
    }

    public function test_the_public_form_is_rate_limited(): void
    {
        $room = MeetingRoom::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->post(route('rooms.public.store', $room->booking_token), $this->payload(['title' => '']));
        }

        $this->post(route('rooms.public.store', $room->booking_token), $this->payload())->assertTooManyRequests();
    }
}
