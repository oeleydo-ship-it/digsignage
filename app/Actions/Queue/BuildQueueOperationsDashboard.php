<?php

namespace App\Actions\Queue;

use App\Enums\QueueTicketStatus;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildQueueOperationsDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, ?int $locationId = null): array
    {
        $today = now()->startOfDay();
        $activeStatuses = [
            QueueTicketStatus::Waiting,
            QueueTicketStatus::Called,
            QueueTicketStatus::Serving,
            QueueTicketStatus::OnHold,
        ];

        $tickets = QueueTicket::query()
            ->forTeam($team)
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->where(function (Builder $query) use ($activeStatuses, $today): void {
                $query->whereIn('status', $activeStatuses)
                    ->orWhere('completed_at', '>=', $today);
            })
            ->with(['service:id,name,location_id', 'counter:id,name,code'])
            ->get();

        $services = QueueService::query()
            ->forTeam($team)
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->with(['location:id,name', 'counters:id,name,code,status,location_id'])
            ->orderBy('name')
            ->get();
        $counters = QueueCounter::query()
            ->forTeam($team)
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->with(['location:id,name', 'services:id,name'])
            ->orderBy('name')
            ->get();

        $waiting = $tickets->where('status', QueueTicketStatus::Waiting);
        $serving = $tickets->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving]);
        $completed = $tickets->where('status', QueueTicketStatus::Completed);
        $noShows = $tickets->where('status', QueueTicketStatus::NoShow);
        $openCounters = $counters->filter(fn (QueueCounter $counter) => $counter->status->allowsCallNext());
        $closedCounters = $counters->reject(fn (QueueCounter $counter) => $counter->status->allowsCallNext());
        $longest = $waiting->sortByDesc(fn (QueueTicket $ticket) => $ticket->waitingDurationSeconds() ?? 0)->first();

        $serviceRows = $services->map(function (QueueService $service) use ($tickets): array {
            $serviceTickets = $tickets->where('queue_service_id', $service->id);
            $waiting = $serviceTickets->where('status', QueueTicketStatus::Waiting);
            $serving = $serviceTickets->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving]);
            $openCounters = $service->counters->filter(fn (QueueCounter $counter) => $counter->status->allowsCallNext())->count();

            return [
                'id' => $service->id,
                'name' => $service->name,
                'location_id' => $service->location_id,
                'location_name' => $service->location_id === null
                    ? __('All locations')
                    : $service->location->name,
                'waiting' => $waiting->count(),
                'serving' => $serving->count(),
                'longest_wait_seconds' => $this->longestWait($waiting),
                'open_counters' => $openCounters,
                'total_counters' => $service->counters->count(),
                'counter_ids' => $service->counters->modelKeys(),
                'open_counter_ids' => $service->counters
                    ->filter(fn (QueueCounter $counter) => $counter->status->allowsCallNext())
                    ->modelKeys(),
                'congestion' => $this->congestion($waiting->count(), $openCounters),
            ];
        })->values();

        $locationRows = $serviceRows
            ->groupBy(fn (array $row) => (string) ($row['location_id'] ?? 0))
            ->map(function (Collection $rows): array {
                $waiting = (int) $rows->sum('waiting');
                $openCounters = $rows->pluck('open_counter_ids')->flatten()->unique()->count();
                $totalCounters = $rows->pluck('counter_ids')->flatten()->unique()->count();

                return [
                    'id' => $rows->first()['location_id'],
                    'name' => $rows->first()['location_name'],
                    'waiting' => $waiting,
                    'serving' => (int) $rows->sum('serving'),
                    'longest_wait_seconds' => (int) $rows->max('longest_wait_seconds'),
                    'open_counters' => $openCounters,
                    'total_counters' => $totalCounters,
                    'congestion' => $this->congestion($waiting, $openCounters),
                    'services' => $rows->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values();

        $counterRows = $counters->map(function (QueueCounter $counter) use ($tickets): array {
            $serviceIds = $counter->services->modelKeys();
            $eligibleWaiting = $tickets
                ->whereIn('queue_service_id', $serviceIds)
                ->where('status', QueueTicketStatus::Waiting);
            $current = $tickets
                ->where('counter_id', $counter->id)
                ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving, QueueTicketStatus::OnHold])
                ->sortByDesc('called_at')
                ->first();

            return [
                'id' => $counter->id,
                'name' => $counter->name,
                'code' => $counter->code,
                'location_name' => $counter->location_id === null
                    ? __('All locations')
                    : $counter->location->name,
                'status' => $counter->status->value,
                'status_label' => $counter->status->label(),
                'services' => $counter->services->pluck('name')->values()->all(),
                'waiting' => $eligibleWaiting->count(),
                'current_ticket' => $current?->number,
                'current_status' => $current?->status->label(),
            ];
        })->values();

        return [
            'stats' => [
                'waiting' => $waiting->count(),
                'serving' => $serving->count(),
                'completed_today' => $completed->count(),
                'no_shows_today' => $noShows->count(),
                'average_wait_seconds' => $this->averageDuration(
                    $tickets,
                    fn (QueueTicket $ticket) => $ticket->waitingDurationSeconds(),
                ),
                'average_service_seconds' => $this->averageDuration(
                    $completed,
                    fn (QueueTicket $ticket) => $ticket->servingDurationSeconds(),
                ),
                'longest_waiting' => $longest === null ? null : [
                    'id' => $longest->id,
                    'number' => $longest->number,
                    'customer_name' => $longest->customer_name,
                    'service_name' => $longest->service->name,
                    'seconds' => $longest->waitingDurationSeconds() ?? 0,
                ],
                'open_counters' => $openCounters->count(),
                'closed_counters' => $closedCounters->count(),
            ],
            'locations' => $locationRows,
            'services' => $serviceRows,
            'counters' => $counterRows,
            'service_ids' => $services->modelKeys(),
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /** @param Collection<int, QueueTicket> $tickets */
    protected function longestWait(Collection $tickets): int
    {
        return (int) $tickets->max(fn (QueueTicket $ticket) => $ticket->waitingDurationSeconds() ?? 0);
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @param  callable(QueueTicket): (?int)  $duration
     */
    protected function averageDuration(Collection $tickets, callable $duration): ?int
    {
        $durations = $tickets
            ->map($duration)
            ->filter(fn (?int $seconds) => $seconds !== null);

        return $durations->isEmpty() ? null : (int) round((float) $durations->average());
    }

    /** @return array{level: string, label: string} */
    protected function congestion(int $waiting, int $openCounters): array
    {
        $ratio = $openCounters > 0 ? $waiting / $openCounters : ($waiting > 0 ? INF : 0);
        $level = match (true) {
            $ratio >= 8 => 'critical',
            $ratio >= 5 => 'high',
            $ratio >= 2 => 'moderate',
            default => 'normal',
        };

        return ['level' => $level, 'label' => ucfirst($level)];
    }
}
