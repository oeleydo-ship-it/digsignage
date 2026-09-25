<?php

namespace Tests\Feature\Booking;

use App\Enums\TeamRole;
use App\Models\MeetingRoom;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MeetingRoomTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Boardroom',
            'location_id' => null,
            'description' => 'Level 3',
            'capacity' => 12,
            'amenities' => 'Display, Whiteboard ,  Video conferencing',
            'color' => '#0ea5e9',
            'opens_at' => '07:00',
            'closes_at' => '20:00',
            'min_duration_minutes' => 15,
            'max_duration_minutes' => 180,
            'is_active' => true,
            'public_booking_enabled' => true,
            'requires_approval' => false,
            'external_calendar_id' => null,
        ], $overrides);
    }

    public function test_admins_can_create_update_and_delete_rooms(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($owner)->post(route('rooms.store', $team), $this->payload())->assertSessionHasNoErrors();

        $room = MeetingRoom::query()->sole();
        $this->assertSame(['Display', 'Whiteboard', 'Video conferencing'], $room->amenities);
        $this->assertSame(40, strlen($room->booking_token));

        $this->actingAs($owner)
            ->patch(route('rooms.update', [$team, $room]), $this->payload(['name' => 'Board room', 'requires_approval' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Board room', $room->refresh()->name);
        $this->assertTrue($room->requires_approval);

        $this->actingAs($owner)
            ->get(route('rooms.index', $team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/rooms')
                ->has('rooms', 1)
                ->where('rooms.0.booking_url', route('rooms.public.show', $room->booking_token)));

        $this->actingAs($owner)->delete(route('rooms.destroy', [$team, $room]))->assertRedirect();
        $this->assertDatabaseCount('meeting_rooms', 0);
    }

    public function test_members_cannot_manage_rooms(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($member)->get(route('rooms.index', $team))->assertOk();
        $this->actingAs($member)->post(route('rooms.store', $team), $this->payload())->assertForbidden();
        $this->actingAs($member)->get(route('bookings.microsoft.show', $team))->assertForbidden();
    }

    public function test_room_rules_are_validated(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('rooms.store', $owner->currentTeam), $this->payload([
                'opens_at' => '18:00',
                'closes_at' => '09:00',
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 30,
                'color' => 'blue',
            ]))
            ->assertSessionHasErrors(['closes_at', 'max_duration_minutes', 'color']);
    }

    public function test_rotating_the_link_revokes_the_old_one(): void
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $room = MeetingRoom::factory()->create(['team_id' => $team->id]);
        $oldToken = $room->booking_token;

        $this->actingAs($owner)->post(route('rooms.rotate-link', [$team, $room]))->assertRedirect();

        $this->assertNotSame($oldToken, $room->refresh()->booking_token);
        $this->get(route('rooms.public.show', $oldToken))->assertNotFound();
        $this->get(route('rooms.public.show', $room->booking_token))->assertOk();
    }
}
