<?php

namespace Database\Factories;

use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use App\Support\QueueTicketNumbering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = QueueService::factory();
        $sequence = fake()->numberBetween(1, 50);

        return [
            'team_id' => Team::factory(),
            'queue_service_id' => $service,
            'location_id' => null,
            'number' => QueueTicketNumbering::format('A', $sequence),
            'sequence' => $sequence,
            'numbering_period' => now()->toDateString(),
            'status' => QueueTicketStatus::Waiting,
            'customer_name' => null,
            'customer_phone' => null,
            'customer_email' => null,
            'priority' => 0,
            'queue_priority_id' => null,
            'queue_position' => 1,
            'called_at' => null,
            'service_started_at' => null,
            'completed_at' => null,
            'counter_id' => null,
            'assigned_user_id' => null,
            'waiting_duration_seconds' => null,
            'serving_duration_seconds' => null,
            'source' => QueueTicketSource::Staff,
            'public_token' => null,
        ];
    }

    /**
     * Bind the ticket to an existing service (and its team).
     */
    public function forService(QueueService $service): static
    {
        return $this->state(fn (): array => [
            'team_id' => $service->team_id,
            'queue_service_id' => $service->id,
            'location_id' => $service->location_id,
            'number' => $service->nextTicketNumber(),
            'numbering_period' => $service->currentPeriodKey(),
        ]);
    }
}
