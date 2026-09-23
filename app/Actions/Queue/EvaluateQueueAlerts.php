<?php

namespace App\Actions\Queue;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Data\QueueAlertConfig;
use App\Enums\PlanFeature;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use App\Enums\SignageAlert;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\Team;
use App\Support\TeamQuota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EvaluateQueueAlerts
{
    public function __construct(
        protected DispatchSignageAlert $alerts,
        protected TeamQuota $quota,
    ) {}

    /** Evaluate enabled queue alert rules and return the number newly queued. */
    public function handle(?Team $team = null): int
    {
        $settings = $team === null
            ? QueueSetting::query()->with('team')->get()
            : collect([QueueSetting::resolveForTeam($team)->load('team')]);
        $queued = 0;

        foreach ($settings as $setting) {
            $owner = $setting->team;

            if (! $this->quota->allowsFeature($owner, PlanFeature::QueueManagement)) {
                continue;
            }

            $config = QueueAlertConfig::resolve($setting->settings ?? []);

            if (! $config->enabled) {
                continue;
            }

            $services = QueueService::query()
                ->forTeam($owner)
                ->where('is_active', true)
                ->with([
                    'tickets' => fn ($query) => $query->where('status', QueueTicketStatus::Waiting->value),
                    'counters',
                ])
                ->get();

            foreach ($services as $service) {
                $queued += $this->evaluateService($owner, $service, $config);
            }

            $counters = QueueCounter::query()->forTeam($owner)->get();

            foreach ($counters as $counter) {
                $queued += $this->evaluateCounter($owner, $counter, $config);
            }
        }

        return $queued;
    }

    protected function evaluateService(Team $team, QueueService $service, QueueAlertConfig $config): int
    {
        /** @var Collection<int, QueueTicket> $waiting */
        $waiting = $service->tickets;
        $waitSeconds = $waiting->map(fn (QueueTicket $ticket): int => $ticket->created_at === null
            ? 0
            : (int) $ticket->created_at->diffInSeconds(now(), true));
        $count = $waiting->count();
        $averageMinutes = $count === 0 ? 0 : (int) floor($waitSeconds->average() / 60);
        $longestMinutes = $count === 0 ? 0 : (int) floor($waitSeconds->max() / 60);
        $availableCounters = $service->counters
            ->filter(fn (QueueCounter $counter): bool => $counter->status->allowsCallNext())
            ->count();
        $queued = 0;
        $data = [
            'service_id' => $service->id,
            'service_name' => $service->name,
            'location_id' => $service->location_id,
        ];

        $queued += $this->emit(
            $team,
            $config->averageWaitMinutes > 0 && $averageMinutes > $config->averageWaitMinutes,
            'average-wait:'.$service->id,
            SignageAlert::QueueAverageWaitHigh,
            'Queue average wait is high',
            "{$service->name} has an average wait of {$averageMinutes} minutes (threshold: {$config->averageWaitMinutes}).",
            $data + ['value' => $averageMinutes, 'threshold' => $config->averageWaitMinutes, 'unit' => 'minutes'],
            $config,
        );
        $queued += $this->emit(
            $team,
            $count > $config->waitingCustomers,
            'waiting-count:'.$service->id,
            SignageAlert::QueueWaitingCountHigh,
            'Queue waiting count is high',
            "{$service->name} has {$count} waiting customers (threshold: {$config->waitingCustomers}).",
            $data + ['value' => $count, 'threshold' => $config->waitingCustomers, 'unit' => 'customers'],
            $config,
        );
        $queued += $this->emit(
            $team,
            $longestMinutes > $config->customerWaitMinutes,
            'customer-wait:'.$service->id,
            SignageAlert::QueueCustomerWaitHigh,
            'A customer has waited too long',
            "The longest wait for {$service->name} is {$longestMinutes} minutes (threshold: {$config->customerWaitMinutes}).",
            $data + ['value' => $longestMinutes, 'threshold' => $config->customerWaitMinutes, 'unit' => 'minutes'],
            $config,
        );
        $queued += $this->emit(
            $team,
            $config->noCounterAvailable && $count > 0 && $availableCounters === 0,
            'no-counter:'.$service->id,
            SignageAlert::QueueNoCounterAvailable,
            'No counter is available',
            "{$service->name} has {$count} waiting customers and no open counter.",
            $data + ['waiting_customers' => $count],
            $config,
        );
        $queued += $this->emit(
            $team,
            $config->capacityReached && $service->max_queue_capacity !== null && $count >= $service->max_queue_capacity,
            'capacity:'.$service->id,
            SignageAlert::QueueCapacityReached,
            'Queue capacity reached',
            "{$service->name} has reached its capacity of {$service->max_queue_capacity} customers.",
            $data + ['value' => $count, 'threshold' => $service->max_queue_capacity, 'unit' => 'customers'],
            $config,
        );

        return $queued;
    }

    protected function evaluateCounter(Team $team, QueueCounter $counter, QueueAlertConfig $config): int
    {
        return $this->emit(
            $team,
            $config->counterOffline && $counter->status === QueueCounterStatus::Paused,
            'counter-offline:'.$counter->id,
            SignageAlert::QueueCounterOffline,
            'Queue counter is offline',
            "{$counter->name} ({$counter->code}) is paused and unavailable.",
            ['counter_id' => $counter->id, 'counter_name' => $counter->name, 'location_id' => $counter->location_id],
            $config,
        );
    }

    /** @param array<string, mixed> $data */
    protected function emit(
        Team $team,
        bool $active,
        string $conditionKey,
        SignageAlert $event,
        string $title,
        string $body,
        array $data,
        QueueAlertConfig $config,
    ): int {
        $cacheKey = "queue-alert-condition:{$team->id}:{$conditionKey}";

        if (! $active) {
            Cache::forget($cacheKey);

            return 0;
        }

        if (! Cache::add($cacheKey, true, $config->cooldownMinutes * 60)) {
            return 0;
        }

        $this->alerts->queue($team, $event, $title, $body, $data);

        return 1;
    }
}
