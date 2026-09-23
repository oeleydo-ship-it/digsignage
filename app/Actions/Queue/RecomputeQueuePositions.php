<?php

namespace App\Actions\Queue;

use App\Models\QueueService;
use App\Models\Team;

class RecomputeQueuePositions
{
    public function __construct(
        protected SelectNextQueueTicket $selectNextQueueTicket,
    ) {
        //
    }

    /**
     * Recalculate waiting positions using the service dispatch strategy.
     */
    public function handle(QueueService $service): void
    {
        $team = $service->relationLoaded('team')
            ? $service->team
            : Team::query()->findOrFail($service->team_id);

        foreach ($this->selectNextQueueTicket->rankedWaiting($team, $service) as $index => $ticket) {
            $position = $index + 1;

            if ($ticket->queue_position !== $position) {
                $ticket->forceFill(['queue_position' => $position])->save();
            }
        }
    }
}
