<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\MeetingRoom;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRoom>
 */
class MeetingRoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'location_id' => null,
            'name' => ucfirst(fake()->unique()->word()).' room',
            'description' => null,
            'capacity' => fake()->numberBetween(4, 20),
            'amenities' => ['Display', 'Video conferencing'],
            'color' => '#2563eb',
            'is_active' => true,
            'public_booking_enabled' => true,
            'requires_approval' => false,
            'booking_token' => MeetingRoom::newBookingToken(),
            'min_duration_minutes' => 15,
            'max_duration_minutes' => 240,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'calendar_connection_id' => null,
            'external_calendar_id' => null,
        ];
    }

    public function requiresApproval(): static
    {
        return $this->state(fn () => ['requires_approval' => true]);
    }

    public function privateOnly(): static
    {
        return $this->state(fn () => ['public_booking_enabled' => false]);
    }

    public function linkedToMicrosoft(CalendarConnection $connection, string $mailbox = 'boardroom@contoso.com'): static
    {
        return $this->state(fn () => [
            'team_id' => $connection->team_id,
            'calendar_connection_id' => $connection->id,
            'external_calendar_id' => $mailbox,
        ]);
    }
}
