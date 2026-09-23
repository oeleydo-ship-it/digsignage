<?php

namespace App\Actions\Queue;

use App\Enums\QueueTicketStatus;
use App\Models\QueueTicket;
use App\Support\QueueAnalyticsFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QueryQueueAnalytics
{
    /** @return array<string, mixed> */
    public function handle(QueueAnalyticsFilters $filters): array
    {
        $tickets = $this->filtered($filters)
            ->with([
                'service:id,name',
                'location:id,name',
                'counter:id,name,code',
                'assignedUser:id,name',
            ])
            ->orderBy('created_at')
            ->get();

        $waitingDurations = $tickets->pluck('waiting_duration_seconds')
            ->filter(fn (mixed $value) => is_int($value))
            ->values();
        $serviceDurations = $tickets->pluck('serving_duration_seconds')
            ->filter(fn (mixed $value) => is_int($value))
            ->values();
        $completed = $tickets->where('status', QueueTicketStatus::Completed);
        $noShows = $tickets->where('status', QueueTicketStatus::NoShow);
        $cancelled = $tickets->where('status', QueueTicketStatus::Cancelled);
        $slaSeconds = ((int) $filters->filters['sla_minutes']) * 60;
        $slaEligible = $waitingDurations->count();
        $slaMet = $waitingDurations->filter(fn (int $seconds) => $seconds <= $slaSeconds)->count();

        $volume = [
            'hour' => $this->volume($tickets, fn (QueueTicket $ticket) => $ticket->created_at?->format('H:00') ?? ''),
            'day' => $this->volume($tickets, fn (QueueTicket $ticket) => $ticket->created_at?->toDateString() ?? ''),
            'week' => $this->volume($tickets, fn (QueueTicket $ticket) => $ticket->created_at?->copy()->startOfWeek()->toDateString() ?? ''),
            'month' => $this->volume($tickets, fn (QueueTicket $ticket) => $ticket->created_at?->format('Y-m') ?? ''),
        ];

        return [
            'summary' => [
                'tickets' => $tickets->count(),
                'completed' => $completed->count(),
                'abandonment_rate' => $this->percentage($cancelled->count(), $tickets->count()),
                'no_show_rate' => $this->percentage($noShows->count(), $tickets->count()),
                'sla_compliance' => $this->percentage($slaMet, $slaEligible),
                'sla_eligible' => $slaEligible,
            ],
            'waiting_time' => $this->durationStats($waitingDurations, true),
            'service_time' => $this->durationStats($serviceDurations, false),
            'volume' => $volume,
            'peak_hours' => collect($volume['hour'])
                ->sortByDesc('value')
                ->take(5)
                ->values()
                ->all(),
            'locations' => $this->locationPerformance($tickets, $slaSeconds),
            'staff' => $this->staffPerformance($tickets),
            'counters' => $this->counterPerformance($tickets),
            'services' => $this->servicePerformance($tickets),
        ];
    }

    /** @return Builder<QueueTicket> */
    public function filtered(QueueAnalyticsFilters $filters): Builder
    {
        return QueueTicket::query()
            ->forTeam($filters->team)
            ->whereBetween('created_at', [$filters->from, $filters->until])
            ->when($filters->filters['location_id'], fn (Builder $query, mixed $id) => $query->where('location_id', $id))
            ->when($filters->filters['service_id'], fn (Builder $query, mixed $id) => $query->where('queue_service_id', $id))
            ->when($filters->filters['counter_id'], fn (Builder $query, mixed $id) => $query->where('counter_id', $id))
            ->when($filters->filters['employee_id'], fn (Builder $query, mixed $id) => $query->where('assigned_user_id', $id));
    }

    /** @return array<string, mixed> */
    public function serialize(QueueTicket $ticket): array
    {
        return [
            'number' => $ticket->number,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'status' => $ticket->status->label(),
            'location' => $ticket->location?->name,
            'service' => $ticket->service->name,
            'counter' => $ticket->counter?->name,
            'employee' => $ticket->assignedUser?->name,
            'waiting_duration_seconds' => $ticket->waiting_duration_seconds,
            'serving_duration_seconds' => $ticket->serving_duration_seconds,
        ];
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @param  callable(QueueTicket): string  $key
     * @return list<array{label: string, value: int}>
     */
    protected function volume(Collection $tickets, callable $key): array
    {
        return array_values($tickets
            ->groupBy($key)
            ->reject(fn (Collection $rows, string $label) => $label === '')
            ->map(fn (Collection $rows, string $label) => ['label' => $label, 'value' => $rows->count()])
            ->sortBy('label')
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, int>  $durations
     * @return array{average: int|null, median: int|null, maximum: int|null, minimum?: int|null, samples: int}
     */
    protected function durationStats(Collection $durations, bool $includeMinimum): array
    {
        $stats = [
            'average' => $durations->isEmpty() ? null : (int) round((float) $durations->average()),
            'median' => $this->median($durations),
            'maximum' => $durations->isEmpty() ? null : (int) $durations->max(),
            'samples' => $durations->count(),
        ];

        if ($includeMinimum) {
            $stats['minimum'] = $durations->isEmpty() ? null : (int) $durations->min();
        }

        return $stats;
    }

    /** @param Collection<int, int> $values */
    protected function median(Collection $values): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);

        if ($sorted->count() % 2 === 1) {
            return (int) $sorted[$middle];
        }

        return (int) round(((int) $sorted[$middle - 1] + (int) $sorted[$middle]) / 2);
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @return list<array{id: int, name: string, tickets_served: int, average_handling_seconds: int|null, no_shows: int}>
     */
    protected function staffPerformance(Collection $tickets): array
    {
        return array_values($tickets->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->map(function (Collection $rows): array {
                $completed = $rows->where('status', QueueTicketStatus::Completed);

                return [
                    'id' => (int) $rows->first()->assigned_user_id,
                    'name' => $rows->first()->assignedUser->name,
                    'tickets_served' => $completed->count(),
                    'average_handling_seconds' => $this->averageField($completed, 'serving_duration_seconds'),
                    'no_shows' => $rows->where('status', QueueTicketStatus::NoShow)->count(),
                ];
            })
            ->sortByDesc('tickets_served')
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @return list<array{id: int, name: string, tickets_served: int, average_wait_seconds: int|null, average_handling_seconds: int|null, no_shows: int}>
     */
    protected function counterPerformance(Collection $tickets): array
    {
        return array_values($tickets->whereNotNull('counter_id')
            ->groupBy('counter_id')
            ->map(function (Collection $rows): array {
                $completed = $rows->where('status', QueueTicketStatus::Completed);

                return [
                    'id' => (int) $rows->first()->counter_id,
                    'name' => $rows->first()->counter->name,
                    'tickets_served' => $completed->count(),
                    'average_wait_seconds' => $this->averageField($rows, 'waiting_duration_seconds'),
                    'average_handling_seconds' => $this->averageField($completed, 'serving_duration_seconds'),
                    'no_shows' => $rows->where('status', QueueTicketStatus::NoShow)->count(),
                ];
            })
            ->sortByDesc('tickets_served')
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @return list<array{id: int, name: string, tickets: int, completed: int, average_wait_seconds: int|null, average_service_seconds: int|null, no_shows: int, abandoned: int}>
     */
    protected function servicePerformance(Collection $tickets): array
    {
        return array_values($tickets->groupBy('queue_service_id')
            ->map(function (Collection $rows): array {
                $completed = $rows->where('status', QueueTicketStatus::Completed);

                return [
                    'id' => (int) $rows->first()->queue_service_id,
                    'name' => $rows->first()->service->name,
                    'tickets' => $rows->count(),
                    'completed' => $completed->count(),
                    'average_wait_seconds' => $this->averageField($rows, 'waiting_duration_seconds'),
                    'average_service_seconds' => $this->averageField($completed, 'serving_duration_seconds'),
                    'no_shows' => $rows->where('status', QueueTicketStatus::NoShow)->count(),
                    'abandoned' => $rows->where('status', QueueTicketStatus::Cancelled)->count(),
                ];
            })
            ->sortByDesc('tickets')
            ->values()
            ->all());
    }

    /**
     * Corporate comparison across every branch in the selected report scope.
     *
     * @param  Collection<int, QueueTicket>  $tickets
     * @return list<array{id: int|null, name: string, tickets: int, completed: int, average_wait_seconds: int|null, average_service_seconds: int|null, sla_compliance: float, no_shows: int, abandoned: int}>
     */
    protected function locationPerformance(Collection $tickets, int $slaSeconds): array
    {
        return array_values($tickets
            ->groupBy(fn (QueueTicket $ticket): string => (string) ($ticket->location_id ?? 0))
            ->map(function (Collection $rows) use ($slaSeconds): array {
                /** @var QueueTicket $first */
                $first = $rows->first();
                $completed = $rows->where('status', QueueTicketStatus::Completed);
                $waits = $rows->pluck('waiting_duration_seconds')->filter(fn (mixed $value) => is_int($value));
                $slaMet = $waits->filter(fn (int $seconds): bool => $seconds <= $slaSeconds)->count();

                return [
                    'id' => $first->location_id,
                    'name' => $first->location_id === null
                        ? __('All locations')
                        : $first->location->name,
                    'tickets' => $rows->count(),
                    'completed' => $completed->count(),
                    'average_wait_seconds' => $this->averageField($rows, 'waiting_duration_seconds'),
                    'average_service_seconds' => $this->averageField($completed, 'serving_duration_seconds'),
                    'sla_compliance' => $this->percentage($slaMet, $waits->count()),
                    'no_shows' => $rows->where('status', QueueTicketStatus::NoShow)->count(),
                    'abandoned' => $rows->where('status', QueueTicketStatus::Cancelled)->count(),
                ];
            })
            ->sortByDesc('tickets')
            ->values()
            ->all());
    }

    /** @param Collection<int, QueueTicket> $tickets */
    protected function averageField(Collection $tickets, string $field): ?int
    {
        $values = $tickets->pluck($field)->filter(fn (mixed $value) => is_int($value));

        return $values->isEmpty() ? null : (int) round((float) $values->average());
    }

    protected function percentage(int $part, int $whole): float
    {
        return $whole === 0 ? 0.0 : round(($part / $whole) * 100, 1);
    }
}
