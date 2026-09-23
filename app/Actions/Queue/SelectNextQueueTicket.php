<?php

namespace App\Actions\Queue;

use App\Data\QueueStarvationConfig;
use App\Enums\QueueStrategy;
use App\Enums\QueueTicketStatus;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class SelectNextQueueTicket
{
    /**
     * Pick the next waiting ticket for a service.
     */
    public function handle(Team $team, QueueService $service): ?QueueTicket
    {
        return $this->rankedWaiting($team, $service)->first();
    }

    /**
     * Pick the next waiting ticket among services a counter can take.
     *
     * @param  iterable<int, QueueService>  $services
     */
    public function handleForServices(Team $team, iterable $services): ?QueueTicket
    {
        return $this->rankedWaitingForServices($team, $services)->first();
    }

    /**
     * Waiting tickets in the order Call Next would consume them.
     *
     * @return Collection<int, QueueTicket>
     */
    public function rankedWaiting(Team $team, QueueService $service): Collection
    {
        return $this->rankAmongServices(
            $this->waitingTickets($team, [$service->id]),
            new BaseCollection([$service->id => $service]),
        );
    }

    /**
     * Waiting tickets across several services, ranked for a shared counter.
     *
     * @param  iterable<int, QueueService>  $services
     * @return Collection<int, QueueTicket>
     */
    public function rankedWaitingForServices(Team $team, iterable $services): Collection
    {
        $keyed = $this->keyedServices($services);

        if ($keyed->isEmpty()) {
            return new Collection;
        }

        return $this->rankAmongServices(
            $this->waitingTickets($team, array_values($keyed->keys()->all())),
            $keyed,
        );
    }

    /**
     * Rank already-loaded (and typically locked) tickets for the given services.
     *
     * @param  Collection<int, QueueTicket>  $tickets
     * @param  iterable<int, QueueService>  $services
     * @return Collection<int, QueueTicket>
     */
    public function rankLocked(Collection $tickets, iterable $services): Collection
    {
        return $this->rankAmongServices($tickets, $this->keyedServices($services));
    }

    /**
     * @param  Collection<int, QueueTicket>  $tickets
     * @param  BaseCollection<int, QueueService>  $servicesById
     * @return Collection<int, QueueTicket>
     */
    public function rankAmongServices(Collection $tickets, BaseCollection $servicesById): Collection
    {
        if ($tickets->isEmpty()) {
            return $tickets->values();
        }

        $allFifo = $servicesById->every(
            fn (QueueService $service): bool => $service->queue_strategy === QueueStrategy::Fifo,
        );

        if ($allFifo) {
            return $tickets
                ->sort(function (QueueTicket $left, QueueTicket $right): int {
                    return ($left->created_at?->getTimestamp() ?? 0) <=> ($right->created_at?->getTimestamp() ?? 0)
                        ?: $left->id <=> $right->id;
                })
                ->values();
        }

        $now = now();
        $teamSettings = [];

        $ranked = $tickets
            ->map(function (QueueTicket $ticket) use ($now, $servicesById, &$teamSettings): array {
                $service = $servicesById->get($ticket->queue_service_id) ?? $ticket->service;
                $waited = $ticket->created_at !== null
                    ? max(0, (int) $ticket->created_at->diffInSeconds($now))
                    : 0;

                if ($service->queue_strategy === QueueStrategy::Fifo) {
                    return [
                        'ticket' => $ticket,
                        'waited' => $waited,
                        'effective' => 0,
                        'starved' => false,
                        'id' => $ticket->id,
                    ];
                }

                $teamId = $service->team_id;
                $teamSettings[$teamId] ??= QueueSetting::resolveForTeam($service->team)->settings ?? [];
                $starvation = QueueStarvationConfig::resolve($service->starvation, $teamSettings[$teamId]);

                $effective = $ticket->priority;

                if ($starvation->promoteAfterSeconds !== null) {
                    $effective += intdiv($waited, $starvation->promoteAfterSeconds);
                }

                $starved = $starvation->maxPriorityWaitSeconds !== null
                    && $waited >= $starvation->maxPriorityWaitSeconds;

                return [
                    'ticket' => $ticket,
                    'waited' => $waited,
                    'effective' => $effective,
                    'starved' => $starved,
                    'id' => $ticket->id,
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['starved'] !== $right['starved']) {
                    return $left['starved'] ? -1 : 1;
                }

                if ($left['starved'] && $right['starved']) {
                    return $right['waited'] <=> $left['waited']
                        ?: $left['id'] <=> $right['id'];
                }

                return $right['effective'] <=> $left['effective']
                    ?: $right['waited'] <=> $left['waited']
                    ?: $left['id'] <=> $right['id'];
            })
            ->values();

        /** @var Collection<int, QueueTicket> $ordered */
        $ordered = new Collection($ranked->map(fn (array $row): QueueTicket => $row['ticket'])->all());

        return $ordered->values();
    }

    /**
     * @param  list<int>  $serviceIds
     * @return Collection<int, QueueTicket>
     */
    protected function waitingTickets(Team $team, array $serviceIds): Collection
    {
        if ($serviceIds === []) {
            return new Collection;
        }

        /** @var Collection<int, QueueTicket> $tickets */
        $tickets = QueueTicket::query()
            ->where('team_id', $team->id)
            ->whereIn('queue_service_id', $serviceIds)
            ->where('status', QueueTicketStatus::Waiting)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $tickets;
    }

    /**
     * @param  iterable<int, QueueService>  $services
     * @return BaseCollection<int, QueueService>
     */
    protected function keyedServices(iterable $services): BaseCollection
    {
        return (new BaseCollection($services))
            ->filter()
            ->unique(fn (QueueService $service): int => $service->id)
            ->keyBy(fn (QueueService $service): int => $service->id);
    }
}
