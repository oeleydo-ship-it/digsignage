<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\WebhookEvent;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueAuditSnapshot;
use App\Support\QueueTransaction;
use Illuminate\Validation\ValidationException;

class OperateQueueDesk
{
    public function __construct(
        protected RecomputeQueuePositions $recomputeQueuePositions,
        protected TransferQueueTicket $transferQueueTicket,
        protected RecordOrganizationAudit $recordOrganizationAudit,
        protected DispatchQueueTicketWebhook $dispatchQueueTicketWebhook,
    ) {
        //
    }

    /**
     * Finish serving the current ticket.
     */
    public function complete(QueueCounter $counter, ?User $actor = null): QueueTicket
    {
        return $this->finishCurrent($counter, QueueTicketStatus::Completed, $actor);
    }

    /**
     * Mark the current ticket as a no-show.
     */
    public function noShow(QueueCounter $counter, ?User $actor = null): QueueTicket
    {
        return $this->finishCurrent($counter, QueueTicketStatus::NoShow, $actor);
    }

    /**
     * Park the current ticket so the desk can call someone else.
     */
    public function hold(QueueCounter $counter, ?User $actor = null): QueueTicket
    {
        return QueueTransaction::run(function () use ($counter, $actor) {
            $ticket = $this->requireCurrent($counter);
            $before = QueueAuditSnapshot::ticket($ticket);
            $now = now();
            $from = $ticket->status;

            $ticket->forceFill([
                'status' => QueueTicketStatus::OnHold,
                'serving_duration_seconds' => $ticket->service_started_at !== null
                    ? (int) $ticket->service_started_at->diffInSeconds($now)
                    : $ticket->serving_duration_seconds,
            ])->save();

            $this->recordStatus($ticket, $from, QueueTicketStatus::OnHold, $actor, [
                'counter_id' => $counter->id,
            ]);
            $this->releaseCounter($counter);

            return $this->auditedTicket($ticket, $actor, AuditAction::QueueTicketHeld, $before);
        });
    }

    /**
     * Re-announce the ticket currently called or serving at this desk.
     */
    public function recall(QueueCounter $counter, ?User $actor = null): QueueTicket
    {
        return QueueTransaction::run(function () use ($counter, $actor) {
            $ticket = $this->requireCurrent($counter);
            $before = QueueAuditSnapshot::ticket($ticket);
            $from = $ticket->status;

            $ticket->forceFill([
                'called_at' => now(),
            ])->save();

            $this->recordStatus($ticket, $from, $ticket->status, $actor, [
                'counter_id' => $counter->id,
                'recalled' => true,
            ]);

            return $this->auditedTicket($ticket, $actor, AuditAction::QueueTicketRecalled, $before);
        });
    }

    /**
     * Bring the latest held ticket at this desk back into service.
     */
    public function resume(QueueCounter $counter, ?User $actor = null): QueueTicket
    {
        return QueueTransaction::run(function () use ($counter, $actor) {
            $this->lockCounter($counter);

            if ($this->lockedCurrent($counter) !== null) {
                throw ValidationException::withMessages([
                    'ticket' => __('Finish, hold, or transfer the current ticket before resuming another.'),
                ]);
            }

            $ticket = QueueTicket::query()
                ->where('counter_id', $counter->id)
                ->where('status', QueueTicketStatus::OnHold)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                throw ValidationException::withMessages([
                    'ticket' => __('There is no held ticket to resume at this counter.'),
                ]);
            }

            $before = QueueAuditSnapshot::ticket($ticket);
            $now = now();
            $from = $ticket->status;

            $ticket->forceFill([
                'status' => QueueTicketStatus::Serving,
                'called_at' => $now,
                'service_started_at' => $now,
                'queue_position' => null,
                'serving_duration_seconds' => null,
            ])->save();

            $this->recordStatus($ticket, $from, QueueTicketStatus::Serving, $actor, [
                'counter_id' => $counter->id,
                'resumed' => true,
            ]);

            if ($counter->status === QueueCounterStatus::Open) {
                $counter->forceFill(['status' => QueueCounterStatus::Busy])->save();
            }

            return $this->auditedTicket($ticket, $actor, AuditAction::QueueTicketResumed, $before);
        });
    }

    /**
     * Move the current ticket onto another service as waiting.
     */
    public function transfer(
        QueueCounter $counter,
        QueueService $service,
        ?User $actor = null,
        ?QueueCounter $destinationCounter = null,
        ?string $reason = null,
    ): QueueTicket {
        return $this->transferQueueTicket->handle(
            $counter,
            $service,
            $actor,
            $destinationCounter,
            $reason,
        );
    }

    protected function finishCurrent(QueueCounter $counter, QueueTicketStatus $to, ?User $actor): QueueTicket
    {
        return QueueTransaction::run(function () use ($counter, $to, $actor) {
            $ticket = $this->requireCurrent($counter);
            $before = QueueAuditSnapshot::ticket($ticket);
            $now = now();
            $from = $ticket->status;

            $ticket->forceFill([
                'status' => $to,
                'completed_at' => $now,
                'queue_position' => null,
                'waiting_duration_seconds' => $ticket->waiting_duration_seconds ?? (
                    $ticket->created_at !== null
                        ? (int) $ticket->created_at->diffInSeconds($ticket->called_at ?? $now)
                        : 0
                ),
                'serving_duration_seconds' => $ticket->service_started_at !== null
                    ? (int) $ticket->service_started_at->diffInSeconds($now)
                    : $ticket->serving_duration_seconds,
            ])->save();

            $this->recordStatus($ticket, $from, $to, $actor, [
                'counter_id' => $counter->id,
            ]);
            $this->releaseCounter($counter);

            $action = $to === QueueTicketStatus::Completed
                ? AuditAction::QueueTicketCompleted
                : AuditAction::QueueTicketNoShow;

            $ticket = $this->auditedTicket($ticket, $actor, $action, $before);
            $event = $to === QueueTicketStatus::Completed
                ? WebhookEvent::TicketCompleted
                : WebhookEvent::TicketNoShow;
            $this->dispatchQueueTicketWebhook->handle($ticket, $event);

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function auditedTicket(
        QueueTicket $ticket,
        ?User $actor,
        AuditAction $action,
        array $before,
    ): QueueTicket {
        $ticket = $ticket->refresh();

        $this->recordOrganizationAudit->handle(
            Team::query()->findOrFail($ticket->team_id),
            $action,
            $actor,
            'queue_ticket',
            $ticket->id,
            $before,
            QueueAuditSnapshot::ticket($ticket),
        );

        return $ticket;
    }

    protected function requireCurrent(QueueCounter $counter): QueueTicket
    {
        $this->lockCounter($counter);

        $ticket = $this->lockedCurrent($counter);

        if ($ticket === null) {
            throw ValidationException::withMessages([
                'ticket' => __('This counter is not serving a ticket.'),
            ]);
        }

        return $ticket;
    }

    protected function lockCounter(QueueCounter $counter): void
    {
        QueueCounter::query()->whereKey($counter->id)->lockForUpdate()->firstOrFail();
        $counter->refresh();
    }

    protected function lockedCurrent(QueueCounter $counter): ?QueueTicket
    {
        return QueueTicket::query()
            ->where('counter_id', $counter->id)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    protected function releaseCounter(QueueCounter $counter): void
    {
        if ($counter->status === QueueCounterStatus::Busy) {
            $counter->forceFill(['status' => QueueCounterStatus::Open])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function recordStatus(
        QueueTicket $ticket,
        QueueTicketStatus $from,
        QueueTicketStatus $to,
        ?User $actor,
        array $extra = [],
    ): void {
        QueueTicketEvent::query()->create([
            'team_id' => $ticket->team_id,
            'queue_ticket_id' => $ticket->id,
            'user_id' => $actor instanceof User ? $actor->id : null,
            'type' => QueueTicketEventType::StatusChanged,
            'payload' => [
                'from' => $from->value,
                'to' => $to->value,
                ...$extra,
            ],
        ]);
    }
}
