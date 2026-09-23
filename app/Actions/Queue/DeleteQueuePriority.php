<?php

namespace App\Actions\Queue;

use App\Models\QueuePriority;
use Illuminate\Validation\ValidationException;

class DeleteQueuePriority
{
    /**
     * Delete a priority level. At least one level must remain for the team.
     */
    public function handle(QueuePriority $priority): void
    {
        $remaining = QueuePriority::query()
            ->where('team_id', $priority->team_id)
            ->count();

        if ($remaining <= 1) {
            throw ValidationException::withMessages([
                'priority' => __('At least one queue priority must remain.'),
            ]);
        }

        $priority->delete();
    }
}
