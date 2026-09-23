<?php

namespace App\Actions\Queue;

use App\Enums\QueueTicketStatus;
use App\Models\QueueCounter;
use App\Models\QueueTicket;
use App\Support\QueueAuditSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClaimRankedQueueTicket
{
    /**
     * Atomically claim the first still-waiting ticket from a ranked snapshot.
     *
     * Multiple workers may hold the same stale snapshot. The conditional
     * update makes exactly one worker win each ticket; losers continue to the
     * next candidate instead of returning a duplicate assignment.
     *
     * @param  Collection<int, QueueTicket>  $ranked
     * @return array{ticket: QueueTicket, before: array<string, mixed>}|null
     */
    public function handle(
        Collection $ranked,
        QueueCounter $counter,
        ?int $actorId,
        CarbonInterface $now,
    ): ?array {
        foreach ($ranked as $candidate) {
            $waited = $candidate->created_at !== null
                ? (int) $candidate->created_at->diffInSeconds($now)
                : 0;

            $affected = QueueTicket::query()
                ->whereKey($candidate->id)
                ->where('status', QueueTicketStatus::Waiting)
                ->update([
                    'status' => QueueTicketStatus::Serving->value,
                    'counter_id' => $counter->id,
                    'assigned_user_id' => $actorId,
                    'called_at' => $now,
                    'service_started_at' => $now,
                    'queue_position' => null,
                    'waiting_duration_seconds' => $waited,
                    'updated_at' => $now,
                ]);

            if ($affected === 1) {
                $before = QueueAuditSnapshot::ticket($candidate);

                return [
                    'ticket' => $candidate->refresh(),
                    'before' => $before,
                ];
            }
        }

        return null;
    }
}
