<?php

namespace Database\Factories;

use App\Enums\RoomBookingSource;
use App\Enums\RoomBookingStatus;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBooking>
 */
class RoomBookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addHour()->startOfHour();

        return [
            'meeting_room_id' => MeetingRoom::factory(),
            'team_id' => fn (array $attributes) => MeetingRoom::query()->whereKey($attributes['meeting_room_id'])->value('team_id'),
            'title' => fake()->sentence(3),
            'organizer_name' => fake()->name(),
            'organizer_email' => fake()->safeEmail(),
            'attendees' => 4,
            'notes' => null,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'status' => RoomBookingStatus::Confirmed,
            'source' => RoomBookingSource::Manual,
            'external_id' => null,
            'external_change_key' => null,
            'created_by' => null,
        ];
    }

    public function between(\DateTimeInterface $start, \DateTimeInterface $end): static
    {
        return $this->state(fn () => ['starts_at' => $start, 'ends_at' => $end]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => RoomBookingStatus::Pending]);
    }
}
