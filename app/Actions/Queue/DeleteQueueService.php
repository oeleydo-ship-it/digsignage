<?php

namespace App\Actions\Queue;

use App\Models\QueueService;

class DeleteQueueService
{
    /**
     * Delete a queue service. Issued tickets cascade with the service.
     */
    public function handle(QueueService $service): void
    {
        $service->delete();
    }
}
