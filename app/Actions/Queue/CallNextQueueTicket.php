<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\WebhookEvent;
use App\Events\QueueUpdated;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\PlayerManifestCache;
use App\Support\QueueAuditSnapshot;
use App\Support\QueueTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CallNextQueueTicket
{
    public function __construct(
        protected SelectNextQueueTicket $selectNextQueueTicket,
        protected ClaimRankedQueueTicket $claimRankedQueueTicket,
        protected OperateQueueDesk $operateQueueDesk,
        protected RecomputeQueuePositions $recomputeQueuePositions,
        protected RecordOrganizationAudit $recordOrganizationAudit,
        protected DispatchQueueTicketWebhook $dispatchQueueTicketWebhook,
    ) {
        //
    }

    /**
     * Atomically assign the next eligible ticket to this counter.
     *
     * Lock order: counter → services (id ASC) → waiting tickets (id ASC).
     * Claim uses UPDATE ... WHERE status = waiting so two desks cannot take
     * the same ticket even when row locks are a no-op (SQLite).
     *
     * API calls remain idempotent by default. The desk can explicitly advance
     * its current ticket before calling the next waiting customer.
     */
    public function handle(QueueCounter $counter, ?User $actor = null, bool $advanceCurrent = false): QueueTicket
    {
        return QueueTransaction::run(function () use ($counter, $actor, $advanceCurrent) {
            /** @var QueueCounter $counter */
            $counter = QueueCounter::query()
                ->whereKey($counter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $counter->status->allowsCallNext()) {
                throw ValidationException::withMessages([
                    'status' => __('This counter is not open for calling tickets.'),
                ]);
            }

            $current = $this->lockedCurrentTicket($counter);

            if ($current !== null && ! $advanceCurrent) {
                return $current;
            }

            if ($current !== null && $actor !== null) {
                Gate::forUser($actor)->authorize('complete', $counter);
            }

            $serviceIds = $counter->services()
                ->orderBy('queue_services.id')
                ->pluck('queue_services.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            if ($serviceIds === []) {
                throw ValidationException::withMessages([
                    'service_ids' => __('This counter has no services assigned.'),
                ]);
            }

            $services = QueueService::query()
                ->whereIn('id', $serviceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($services->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_ids' => __('This counter has no services assigned.'),
                ]);
            }

            $waiting = QueueTicket::query()
                ->where('team_id', $counter->team_id)
                ->whereIn('queue_service_id', $services->modelKeys())
                ->where('status', QueueTicketStatus::Waiting)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $ranked = $this->selectNextQueueTicket->rankLocked($waiting, $services);
            if ($ranked->isEmpty()) {
                throw ValidationException::withMessages([
                    'ticket' => __('There are no waiting tickets for this counter.'),
                ]);
            }

            if ($current !== null) {
                $this->operateQueueDesk->complete($counter, $actor);
                $counter->refresh();
            }

            $now = now();
            $actorId = $actor instanceof User ? $actor->id : $counter->assigned_user_id;
            $claim = $this->claimRankedQueueTicket->handle($ranked, $counter, $actorId, $now);
            $claimed = $claim['ticket'] ?? null;
            $before = $claim['before'] ?? null;

            if ($claimed === null) {
                throw ValidationException::withMessages([
                    'ticket' => __('There are no waiting tickets for this counter.'),
                ]);
            }

            // The atomic conditional update intentionally bypasses Eloquent
            // model events, so publish and invalidate its realtime effects
            // explicitly after a successful claim.
            PlayerManifestCache::bumpTeam($claimed->team_id);
            // ClaimRankedQueueTicket uses a conditional UPDATE, so it bypasses
            // the model's cache invalidation hook. Bump after commit too: a
            // manifest built during the transition must not survive it.
            DB::afterCommit(fn () => PlayerManifestCache::bumpTeam($claimed->team_id));
            event(new QueueUpdated(
                teamId: $claimed->team_id,
                serviceId: $claimed->queue_service_id,
                ticketId: $claimed->id,
                ticketNumber: $claimed->number,
                status: QueueTicketStatus::Called->value,
                counterId: $counter->id,
                locationId: $claimed->location_id,
                counterName: $counter->name,
                calledAt: $claimed->called_at?->toIso8601String(),
            ));

            QueueTicketEvent::query()->create([
                'team_id' => $claimed->team_id,
                'queue_ticket_id' => $claimed->id,
                'user_id' => $actor instanceof User ? $actor->id : null,
                'type' => QueueTicketEventType::StatusChanged,
                'payload' => [
                    'from' => QueueTicketStatus::Waiting->value,
                    'to' => QueueTicketStatus::Called->value,
                    'counter_id' => $counter->id,
                ],
            ]);

            QueueTicketEvent::query()->create([
                'team_id' => $claimed->team_id,
                'queue_ticket_id' => $claimed->id,
                'user_id' => $actor instanceof User ? $actor->id : null,
                'type' => QueueTicketEventType::StatusChanged,
                'payload' => [
                    'from' => QueueTicketStatus::Called->value,
                    'to' => QueueTicketStatus::Serving->value,
                    'counter_id' => $counter->id,
                ],
            ]);

            if ($counter->status === QueueCounterStatus::Open) {
                $counter->forceFill(['status' => QueueCounterStatus::Busy])->save();
            }

            $this->recomputeQueuePositions->handle($claimed->service);

            $this->recordOrganizationAudit->handle(
                Team::query()->findOrFail($claimed->team_id),
                AuditAction::QueueTicketCalled,
                $actor,
                'queue_ticket',
                $claimed->id,
                $before,
                QueueAuditSnapshot::ticket($claimed),
            );
            $this->dispatchQueueTicketWebhook->handle(
                $claimed,
                WebhookEvent::TicketCalled,
                ['status' => QueueTicketStatus::Called->value],
            );
            $this->dispatchQueueTicketWebhook->handle(
                $claimed,
                WebhookEvent::TicketServing,
                ['status' => QueueTicketStatus::Serving->value],
            );

            return $claimed;
        });
    }

    protected function lockedCurrentTicket(QueueCounter $counter): ?QueueTicket
    {
        return QueueTicket::query()
            ->where('counter_id', $counter->id)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }
}
