<?php

namespace Database\Factories;

use App\Enums\QueueTicketEventType;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicketEvent>
 */
class QueueTicketEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'queue_ticket_id' => QueueTicket::factory(),
            'user_id' => null,
            'type' => QueueTicketEventType::Created,
            'payload' => ['status' => 'waiting'],
        ];
    }
}
