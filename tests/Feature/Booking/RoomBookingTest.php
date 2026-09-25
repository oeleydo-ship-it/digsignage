<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Enums\TeamRole;
use App\Events\PlayerManifestUpdated;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\Screen;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
    }

    protected function member(Team $team, TeamRole $role = TeamRole::Member): User
    {
        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(MeetingRoom $room, array $overrides = []): array
    {
        return array_merge([
            'meeting_room_id' => $room->id,
            'title' => 'Sprint planning',
            'date' => '2026-09-28',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'organizer_name' => null,
            'organizer_email' => null,
            'attendees' => 4,
            'notes' => null,
        ], $overrides);
    }

    public function test_the_day_view_lists_rooms_and_their_bookings(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id, 'name' => 'Boardroom']);
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 09:00'),
            CarbonImmutable::parse('2026-09-28 10:00'),
        )->create(['title' => 'Stand-up']);
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-29 09:00'),
            CarbonImmutable::parse('2026-09-29 10:00'),
        )->create(['title' => 'Tomorrow']);

        $this->actingAs($owner)
            ->get(route('bookings.index', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/index')
                ->where('date', '2026-09-28')
                ->has('rooms', 1)
                ->where('rooms.0.name', 'Boardroom')
                ->has('bookings', 1)
                ->where('bookings.0.title', 'Stand-up')
                ->where('bookings.0.start_time', '09:00')
                ->where('permissions.canManageRooms', true));
    }

    public function test_week_and_month_views_cover_their_whole_range(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);

        foreach ([
            'Sunday before' => '2026-09-27',
            'Monday' => '2026-09-28',
            'Sunday' => '2026-10-04',
            'Next Monday' => '2026-10-05',
            'October 31' => '2026-10-31',
            'November 2' => '2026-11-02',
        ] as $title => $day) {
            RoomBooking::factory()->for($room, 'room')->between(
                CarbonImmutable::parse("{$day} 09:00"),
                CarbonImmutable::parse("{$day} 10:00"),
            )->create(['title' => $title]);
        }

        $titles = fn (Assert $page, array $expected) => $page->where(
            'bookings',
            fn ($bookings) => collect($bookings)->pluck('title')->sort()->values()->all() === collect($expected)->sort()->values()->all(),
        );

        // Week of Wed 30 Sep: Monday 28 Sep – Sunday 4 Oct.
        $this->actingAs($owner)
            ->get(route('bookings.index', [$team, 'date' => '2026-09-30', 'view' => 'week']))
            ->assertInertia(fn (Assert $page) => $titles($page
                ->where('view', 'week')
                ->where('range', ['start' => '2026-09-28', 'end' => '2026-10-04']), ['Monday', 'Sunday']));

        // October 2026 grid: Monday 28 Sep – Sunday 1 Nov.
        $this->actingAs($owner)
            ->get(route('bookings.index', [$team, 'date' => '2026-10-15', 'view' => 'month']))
            ->assertInertia(fn (Assert $page) => $titles($page
                ->where('view', 'month')
                ->where('range', ['start' => '2026-09-28', 'end' => '2026-11-01']), ['Monday', 'Sunday', 'Next Monday', 'October 31']));

        // Unknown views fall back to the day.
        $this->actingAs($owner)
            ->get(route('bookings.index', [$team, 'date' => '2026-09-28', 'view' => 'year']))
            ->assertInertia(fn (Assert $page) => $titles($page->where('view', 'day'), ['Monday']));
    }

    public function test_a_member_can_book_a_free_room(): void
    {
        $team = Team::factory()->create();
        $member = $this->member($team);
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->post(route('bookings.store', $team), $this->payload($room))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $booking = RoomBooking::query()->sole();

        $this->assertSame('Sprint planning', $booking->title);
        $this->assertSame(RoomBookingStatus::Confirmed, $booking->status);
        $this->assertSame(RoomBookingSource::Manual, $booking->source);
        $this->assertSame($member->id, $booking->created_by);
        $this->assertSame($member->name, $booking->organizer_name);
        $this->assertSame('2026-09-28 10:00:00', $booking->starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_overlapping_bookings_are_rejected_but_back_to_back_is_fine(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id, 'name' => 'Boardroom']);

        $this->actingAs($owner)->post(route('bookings.store', $team), $this->payload($room))->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '10:30', 'end_time' => '11:30']))
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '11:00', 'end_time' => '12:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, RoomBooking::query()->count());
    }

    public function test_members_must_follow_room_rules_that_managers_can_override(): void
    {
        $team = Team::factory()->create();
        $member = $this->member($team);
        $admin = $this->member($team, TeamRole::Admin);
        $room = MeetingRoom::factory()->create([
            'team_id' => $team->id,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
            'max_duration_minutes' => 120,
            'capacity' => 6,
        ]);

        $this->actingAs($member)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '18:00', 'end_time' => '19:00']))
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($member)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '09:00', 'end_time' => '13:00']))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($member)
            ->post(route('bookings.store', $team), $this->payload($room, ['attendees' => 12]))
            ->assertSessionHasErrors('attendees');

        $this->actingAs($admin)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '18:00', 'end_time' => '22:00', 'attendees' => 12]))
            ->assertSessionHasNoErrors();
    }

    public function test_bookings_cannot_end_in_the_past(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), $this->payload($room, ['start_time' => '06:00', 'end_time' => '07:00']))
            ->assertSessionHasErrors('starts_at');
    }

    public function test_members_can_cancel_their_own_bookings_but_not_others(): void
    {
        $team = Team::factory()->create();
        $alice = $this->member($team);
        $bob = $this->member($team);
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $booking = RoomBooking::factory()->for($room, 'room')->create(['created_by' => $alice->id]);

        $this->actingAs($bob)
            ->post(route('bookings.cancel', [$team, $booking]))
            ->assertForbidden();

        $this->actingAs($alice)
            ->post(route('bookings.cancel', [$team, $booking]))
            ->assertRedirect();

        $this->assertSame(RoomBookingStatus::Cancelled, $booking->refresh()->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_a_cancelled_booking_frees_the_slot(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)->post(route('bookings.store', $team), $this->payload($room));
        $booking = RoomBooking::query()->sole();
        $this->actingAs($owner)->post(route('bookings.cancel', [$team, $booking]));

        $this->actingAs($owner)
            ->post(route('bookings.store', $team), $this->payload($room, ['title' => 'Rebooked']))
            ->assertSessionHasNoErrors();
    }

    public function test_bookings_can_be_rescheduled_and_moved_to_another_room(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $roomA = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $roomB = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $this->actingAs($owner)->post(route('bookings.store', $team), $this->payload($roomA));
        $booking = RoomBooking::query()->sole();

        // Rescheduling onto its own slot must not clash with itself.
        $this->actingAs($owner)
            ->patch(route('bookings.update', [$team, $booking]), $this->payload($roomA, ['end_time' => '11:30']))
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->patch(route('bookings.update', [$team, $booking]), $this->payload($roomB, ['title' => 'Moved']))
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame($roomB->id, $booking->meeting_room_id);
        $this->assertSame('Moved', $booking->title);
    }

    public function test_managers_approve_or_decline_pending_requests(): void
    {
        $team = Team::factory()->create();
        $member = $this->member($team);
        $admin = $this->member($team, TeamRole::Admin);
        $room = MeetingRoom::factory()->requiresApproval()->create(['team_id' => $team->id]);
        $first = RoomBooking::factory()->for($room, 'room')->pending()->create();
        $second = RoomBooking::factory()->for($room, 'room')->pending()->between(
            CarbonImmutable::parse('2026-09-28 15:00'),
            CarbonImmutable::parse('2026-09-28 16:00'),
        )->create();

        $this->actingAs($member)
            ->post(route('bookings.approve', [$team, $first]))
            ->assertForbidden();

        $this->actingAs($admin)->post(route('bookings.approve', [$team, $first]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('bookings.decline', [$team, $second]))->assertSessionHasNoErrors();

        $this->assertSame(RoomBookingStatus::Confirmed, $first->refresh()->status);
        $this->assertSame(RoomBookingStatus::Declined, $second->refresh()->status);
    }

    public function test_bookings_from_another_team_are_not_reachable(): void
    {
        $owner = User::factory()->create();
        $otherRoom = MeetingRoom::factory()->create();
        $booking = RoomBooking::factory()->for($otherRoom, 'room')->create();

        $this->actingAs($owner)
            ->post(route('bookings.cancel', [$owner->currentTeam, $booking]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('bookings.store', $owner->currentTeam), $this->payload($otherRoom))
            ->assertSessionHasErrors('meeting_room_id');
    }

    public function test_booking_changes_refresh_the_teams_screens(): void
    {
        Event::fake([PlayerManifestUpdated::class]);

        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $screen = Screen::factory()->paired()->create(['team_id' => $team->id]);
        Screen::factory()->paired()->create();

        $this->actingAs($owner)->post(route('bookings.store', $team), $this->payload($room));

        Event::assertDispatched(PlayerManifestUpdated::class, fn (PlayerManifestUpdated $event) => $event->deviceUuid === $screen->device_uuid);
        Event::assertDispatchedTimes(PlayerManifestUpdated::class, 1);
    }
}
