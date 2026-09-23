<?php

namespace App\Support;

use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

class QueueSnapshot
{
    /**
     * Build signage-safe queue data without exposing customer details.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function forTeam(Team $team, array $settings): array
    {
        $serviceId = max(0, (int) ($settings['service_id'] ?? 0));
        $locationId = max(0, (int) ($settings['location_id'] ?? 0));
        $counterId = max(0, (int) ($settings['counter_id'] ?? 0));
        $limit = max(1, min(20, (int) ($settings['limit'] ?? 5)));

        $counter = $counterId > 0
            ? QueueCounter::query()->forTeam($team)->whereKey($counterId)->first()
            : null;

        $services = QueueService::query()
            ->forTeam($team)
            ->where('is_active', true)
            ->when($serviceId > 0, fn (Builder $query) => $query->whereKey($serviceId))
            ->when($locationId > 0, fn (Builder $query) => $query->where('location_id', $locationId))
            ->when($counter, fn (Builder $query) => $query->whereHas(
                'counters',
                fn (Builder $query) => $query->whereKey($counter->id),
            ))
            ->orderBy('name')
            ->get(['id', 'name', 'location_id', 'average_service_duration_seconds', 'max_queue_capacity']);

        $serviceIds = $services->modelKeys();
        $tickets = QueueTicket::query()
            ->forTeam($team)
            ->whereIn('queue_service_id', $serviceIds)
            ->when($locationId > 0, fn (Builder $query) => $query->where('location_id', $locationId));
        $counterTickets = (clone $tickets)
            ->when($counter, fn (Builder $query) => $query->where('counter_id', $counter->id));

        $nowServing = (clone $counterTickets)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->with(['service:id,name', 'counter:id,name,code'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (QueueTicket $ticket) => $this->ticketRow($ticket))
            ->values();

        $recentlyCalled = (clone $counterTickets)
            ->whereNotNull('called_at')
            ->with(['service:id,name', 'counter:id,name,code'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (QueueTicket $ticket) => $this->ticketRow($ticket))
            ->values();

        $waiting = (clone $tickets)
            ->where('status', QueueTicketStatus::Waiting)
            ->with('service:id,name')
            ->orderBy('queue_position')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (QueueTicket $ticket) => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'service' => $ticket->service->name,
                'position' => $ticket->queue_position,
                'waiting_seconds' => $ticket->waitingDurationSeconds(),
            ])
            ->values();

        $today = now()->startOfDay();
        $waitingCount = (clone $tickets)->where('status', QueueTicketStatus::Waiting)->count();
        $servingCount = (clone $counterTickets)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->count();
        $completedToday = (clone $counterTickets)
            ->where('status', QueueTicketStatus::Completed)
            ->where('completed_at', '>=', $today)
            ->count();
        $noShowsToday = (clone $counterTickets)
            ->where('status', QueueTicketStatus::NoShow)
            ->where('completed_at', '>=', $today)
            ->count();

        $counterQuery = QueueCounter::query()
            ->forTeam($team)
            ->when($counter, fn (Builder $query) => $query->whereKey($counter->id))
            ->when($locationId > 0, fn (Builder $query) => $query->where('location_id', $locationId))
            ->when($serviceIds !== [], fn (Builder $query) => $query->whereHas(
                'services',
                fn (Builder $query) => $query->whereIn('queue_services.id', $serviceIds),
            ));

        $openCounters = (clone $counterQuery)
            ->whereIn('status', [QueueCounterStatus::Open, QueueCounterStatus::Busy])
            ->count();
        $closedCounters = (clone $counterQuery)
            ->whereIn('status', [QueueCounterStatus::Closed, QueueCounterStatus::Paused])
            ->count();

        $averageDuration = (int) round((float) ($services->avg('average_service_duration_seconds') ?? 0));
        $estimatedWaitMinutes = (int) ceil(($waitingCount * $averageDuration) / max(1, $openCounters) / 60);
        $serviceName = $services->count() === 1 ? $services->first()?->name : 'All services';
        $joinParameters = ['team' => $team];

        if ($serviceId > 0) {
            $joinParameters['service'] = $serviceId;
        } elseif ($locationId > 0) {
            $joinParameters['location'] = $locationId;
        }

        $status = $services->isEmpty()
            ? 'closed'
            : ($waitingCount > 0 ? 'busy' : 'open');

        return [
            'service_name' => $serviceName,
            'counter_name' => $counter?->name,
            'status' => $status,
            'status_label' => ucfirst($status),
            'now_serving' => $nowServing,
            'recently_called' => $recentlyCalled,
            'waiting' => $waiting,
            'next_waiting' => $waiting->first(),
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'join_url' => route('queue.virtual.show', $joinParameters),
            'ticker' => $nowServing
                ->map(fn (array $row) => $row['number'].' → '.($row['counter'] ?: 'Desk'))
                ->implode('   •   '),
            'stats' => [
                'waiting' => $waitingCount,
                'serving' => $servingCount,
                'completed_today' => $completedToday,
                'no_show_today' => $noShowsToday,
                'open_counters' => $openCounters,
                'closed_counters' => $closedCounters,
            ],
        ];
    }

    /**
     * @return array{id: int, number: string, service: string, counter: string|null, counter_id: int|null, status: string, called_at: string|null}
     */
    protected function ticketRow(QueueTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'service' => $ticket->service->name,
            'counter' => $ticket->counter?->name ?? $ticket->counter?->code,
            'counter_id' => $ticket->counter_id,
            'status' => $ticket->status->value,
            'called_at' => $ticket->called_at?->toIso8601String(),
        ];
    }
}
