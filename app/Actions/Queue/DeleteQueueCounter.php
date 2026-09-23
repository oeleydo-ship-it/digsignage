<?php

namespace App\Actions\Queue;

use App\Events\QueueUpdated;
use App\Models\QueueCounter;

class DeleteQueueCounter
{
    /**
     * Delete a counter. Ticket history keeps the row with a null counter.
     */
    public function handle(QueueCounter $counter): void
    {
        $serviceIds = $counter->services()->pluck('queue_services.id')->all();
        $teamId = $counter->team_id;
        $counterId = $counter->id;
        $locationId = $counter->location_id;
        $counterName = $counter->name;
        $counter->delete();

        foreach ($serviceIds as $serviceId) {
            event(new QueueUpdated(
                teamId: $teamId,
                serviceId: (int) $serviceId,
                counterId: $counterId,
                locationId: $locationId,
                counterName: $counterName,
            ));
        }
    }
}
