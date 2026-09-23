<?php

namespace Database\Factories;

use App\Enums\QueueAppointmentStatus;
use App\Models\QueueAppointment;
use App\Models\QueueService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<QueueAppointment> */
class QueueAppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => fn (array $attributes) => QueueService::find($attributes['queue_service_id'])?->team_id,
            'queue_service_id' => QueueService::factory(),
            'location_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'scheduled_at' => now()->addHour(),
            'reference' => Str::upper(Str::random(10)),
            'status' => QueueAppointmentStatus::Scheduled,
        ];
    }
}
