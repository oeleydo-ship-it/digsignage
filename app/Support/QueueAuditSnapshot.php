<?php

namespace App\Support;

use App\Models\QueueCounter;
use App\Models\QueuePriority;
use App\Models\QueueTicket;

final class QueueAuditSnapshot
{
    /**
     * Return operational ticket fields without customer contact details.
     *
     * @return array<string, mixed>
     */
    public static function ticket(QueueTicket $ticket): array
    {
        return [
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'queue_service_id' => $ticket->queue_service_id,
            'location_id' => $ticket->location_id,
            'counter_id' => $ticket->counter_id,
            'assigned_user_id' => $ticket->assigned_user_id,
            'queue_priority_id' => $ticket->queue_priority_id,
            'priority' => $ticket->priority,
            'queue_position' => $ticket->queue_position,
            'source' => $ticket->source->value,
        ];
    }

    /** @return array<string, mixed> */
    public static function priority(QueuePriority $priority): array
    {
        return [
            'name' => $priority->name,
            'code' => $priority->code,
            'weight' => $priority->weight,
            'color' => $priority->color,
            'sort_order' => $priority->sort_order,
            'is_active' => $priority->is_active,
        ];
    }

    /** @return array<string, mixed> */
    public static function counter(QueueCounter $counter): array
    {
        return [
            'name' => $counter->name,
            'code' => $counter->code,
            'status' => $counter->status->value,
            'location_id' => $counter->location_id,
            'assigned_user_id' => $counter->assigned_user_id,
            'service_ids' => $counter->services()
                ->orderBy('queue_services.id')
                ->pluck('queue_services.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
        ];
    }
}
