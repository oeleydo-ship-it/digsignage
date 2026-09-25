<?php

namespace Tests\Feature\Booking;

use App\Enums\RoomBookingStatus;
use App\Models\Design;
use App\Models\Location;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\Team;
use App\Models\User;
use App\Widgets\WidgetCatalog;
use App\Widgets\WidgetContext;
use App\Widgets\WidgetRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoomWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
    }

    protected function context(Team $team): WidgetContext
    {
        return new WidgetContext($team, 'UTC', CarbonImmutable::now());
    }

    public function test_room_status_carries_todays_confirmed_meetings_in_room_time(): void
    {
        $team = Team::factory()->create();
        $location = Location::factory()->create(['team_id' => $team->id, 'timezone' => 'Asia/Dubai', 'name' => 'HQ']);
        $room = MeetingRoom::factory()->create(['team_id' => $team->id, 'location_id' => $location->id, 'name' => 'Boardroom']);

        // 06:00-07:00 UTC is 10:00-11:00 in Dubai.
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-28 06:00'),
            CarbonImmutable::parse('2026-09-28 07:00'),
        )->create(['title' => 'Leadership']);
        RoomBooking::factory()->for($room, 'room')->pending()->create(['title' => 'Unapproved']);
        RoomBooking::factory()->for($room, 'room')->create(['title' => 'Gone', 'status' => RoomBookingStatus::Cancelled]);
        RoomBooking::factory()->for($room, 'room')->between(
            CarbonImmutable::parse('2026-09-29 06:00'),
            CarbonImmutable::parse('2026-09-29 07:00'),
        )->create(['title' => 'Tomorrow']);

        $data = WidgetCatalog::roomStatus(['room_id' => (string) $room->id], $this->context($team));

        $this->assertSame('Boardroom', $data['room']['name']);
        $this->assertSame('HQ', $data['room']['location']);
        $this->assertSame('Asia/Dubai', $data['room_timezone']);
        $this->assertSame(['Leadership'], array_column($data['bookings'], 'title'));
        $this->assertSame('10:00', $data['bookings'][0]['start_label']);
        $this->assertSame(route('rooms.public.show', $room->booking_token), $data['booking_url']);
    }

    public function test_room_status_cannot_read_another_teams_room(): void
    {
        $team = Team::factory()->create();
        $foreign = MeetingRoom::factory()->create();

        $data = WidgetCatalog::roomStatus(['room_id' => (string) $foreign->id], $this->context($team));

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayNotHasKey('room', $data);
    }

    public function test_room_board_lists_active_rooms_for_a_location(): void
    {
        $team = Team::factory()->create();
        $floor = Location::factory()->create(['team_id' => $team->id]);
        MeetingRoom::factory()->create(['team_id' => $team->id, 'location_id' => $floor->id, 'name' => 'Alpha']);
        MeetingRoom::factory()->create(['team_id' => $team->id, 'location_id' => $floor->id, 'name' => 'Beta', 'is_active' => false]);
        MeetingRoom::factory()->create(['team_id' => $team->id, 'name' => 'Elsewhere']);

        $data = WidgetCatalog::roomBoard(['location_id' => (string) $floor->id], $this->context($team));

        $this->assertSame(['Alpha'], array_map(fn (array $room) => $room['room']['name'], $data['rooms']));
    }

    public function test_the_designer_room_picker_lists_only_the_teams_rooms(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id, 'name' => 'Boardroom']);
        MeetingRoom::factory()->create(['name' => 'Someone else']);

        $widgets = collect(app(WidgetRegistry::class)->toArrayForTeam($team))->keyBy('key');
        $options = collect($widgets['room_status']['schema'])->firstWhere('name', 'room_id')['options'];

        $this->assertSame(['0', (string) $room->id], array_column($options, 'value'));
        $this->assertSame('Boardroom', $options[1]['label']);
    }

    public function test_the_cached_room_picker_refreshes_when_rooms_change(): void
    {
        $team = Team::factory()->create();
        $registry = app(WidgetRegistry::class);
        $roomIds = fn () => array_column(
            collect(collect($registry->toArrayForTeam($team))->keyBy('key')['room_status']['schema'])
                ->firstWhere('name', 'room_id')['options'],
            'value',
        );

        $this->assertSame(['0'], $roomIds());

        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $this->assertSame(['0', (string) $room->id], $roomIds());

        $room->delete();
        $this->assertSame(['0'], $roomIds());
    }

    public function test_only_editor_pages_carry_the_room_picker(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $design = Design::factory()->create(['team_id' => $team->id]);

        $roomOptions = fn ($widgets) => array_column(
            collect(collect($widgets)->keyBy('key')['room_status']['schema'])->firstWhere('name', 'room_id')['options'],
            'value',
        );

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('designs.edit', [$team, $design]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'widgets',
                fn ($widgets) => $roomOptions($widgets) === ['0', (string) $room->id],
            ));

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('dashboard', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where(
                'widgets',
                fn ($widgets) => $roomOptions($widgets) === ['0'],
            ));
    }

    public function test_room_widgets_are_resolved_into_designs(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        RoomBooking::factory()->for($room, 'room')->create(['title' => 'On screen']);

        $this->actingAs($owner)
            ->postJson(route('widgets.resolve', $team), [
                'timezone' => 'UTC',
                'elements' => [[
                    'id' => 'sign',
                    'type' => 'room_status',
                    'props' => ['room_id' => (string) $room->id],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('widgets.sign.data.bookings.0.title', 'On screen');
    }
}
