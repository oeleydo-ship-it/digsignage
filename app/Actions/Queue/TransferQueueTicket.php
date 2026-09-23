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

class TransferQueueTicket
{
    public function __construct(
        protected RecomputeQueuePositions $recomputeQueuePositions,
        protected RecordOrganizationAudit $recordOrganizationAudit,
        protected DispatchQueueTicketWebhook $dispatchQueueTicketWebhook,
    ) {
        //
    }

    /**
     * Move a ticket currently at a desk onto another service (and optional counter).
     *
     * The ticket keeps its number and journey events. It re-enters the destination
     * as waiting so Call Next can pick it up there.
     */
    public function handle(
        QueueCounter $fromCounter,
        QueueService $destinationService,
        ?User $actor = null,
        ?QueueCounter $destinationCounter = null,
        ?string $reason = null,
    ): QueueTicket {
        return QueueTransaction::run(function () use ($fromCounter, $destinationService, $actor, $destinationCounter, $reason) {
            QueueCounter::query()->whereKey($fromCounter->id)->lockForUpdate()->firstOrFail();
            $fromCounter->refresh();

            $ticket = QueueTicket::query()
                ->where('counter_id', $fromCounter->id)
                ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                throw ValidationException::withMessages([
                    'ticket' => __('This counter is not serving a ticket.'),
                ]);
            }

            if ($ticket->status->isClosed()) {
                throw ValidationException::withMessages([
                    'ticket' => __('Completed tickets cannot be transferred.'),
                ]);
            }

            $this->assertDestination($fromCounter, $ticket, $destinationService, $destinationCounter);

            $before = QueueAuditSnapshot::ticket($ticket);
            $from = $ticket->status;
            $previousServiceId = $ticket->queue_service_id;
            $previousService = $ticket->service()->firstOrFail();
            $now = now();
            $reasonText = is_string($reason) ? trim($reason) : '';

            QueueTicketEvent::query()->create([
                'team_id' => $ticket->team_id,
                'queue_ticket_id' => $ticket->id,
                'user_id' => $actor instanceof User ? $actor->id : null,
                'type' => QueueTicketEventType::Transferred,
                'payload' => [
                    'from' => $from->value,
                    'to' => QueueTicketStatus::Waiting->value,
                    'previous_service_id' => $previousServiceId,
                    'new_service_id' => $destinationService->id,
                    'previous_service_name' => $previousService->name,
                    'new_service_name' => $destinationService->name,
                    'previous_counter_id' => $fromCounter->id,
                    'new_counter_id' => $destinationCounter?->id,
                    'previous_counter_name' => $fromCounter->name,
                    'new_counter_name' => $destinationCounter?->name,
                    'reason' => $reasonText !== '' ? $reasonText : null,
                    'number' => $ticket->number,
                ],
            ]);

            $ticket->forceFill([
                'status' => QueueTicketStatus::Waiting,
                'queue_service_id' => $destinationService->id,
                'location_id' => $destinationService->location_id,
                'counter_id' => null,
                'assigned_user_id' => null,
                'called_at' => null,
                'service_started_at' => null,
                'completed_at' => null,
                'queue_position' => 0,
                'waiting_duration_seconds' => $ticket->waiting_duration_seconds ?? (
                    $ticket->created_at !== null
                        ? (int) $ticket->created_at->diffInSeconds($ticket->called_at ?? $now)
                        : null
                ),
                'serving_duration_seconds' => $ticket->service_started_at !== null
                    ? (int) $ticket->service_started_at->diffInSeconds($now)
                    : $ticket->serving_duration_seconds,
            ])->save();

            if ($fromCounter->status === QueueCounterStatus::Busy) {
                $fromCounter->forceFill(['status' => QueueCounterStatus::Open])->save();
            }

            $this->recomputeQueuePositions->handle($previousService);
            $this->recomputeQueuePositions->handle($destinationService);

            $ticket = $ticket->refresh();

            $this->recordOrganizationAudit->handle(
                Team::query()->findOrFail($ticket->team_id),
                AuditAction::QueueTicketTransferred,
                $actor,
                'queue_ticket',
                $ticket->id,
                $before,
                QueueAuditSnapshot::ticket($ticket),
            );
            $this->dispatchQueueTicketWebhook->handle(
                $ticket,
                WebhookEvent::TicketTransferred,
                [
                    'previous_service_id' => $previousServiceId,
                    'new_service_id' => $destinationService->id,
                    'previous_counter_id' => $fromCounter->id,
                    'new_counter_id' => $destinationCounter?->id,
                    'reason' => $reasonText !== '' ? $reasonText : null,
                ],
            );

            return $ticket;
        });
    }

    protected function assertDestination(
        QueueCounter $fromCounter,
        QueueTicket $ticket,
        QueueService $destinationService,
        ?QueueCounter $destinationCounter,
    ): void {
        if ($destinationService->team_id !== $fromCounter->team_id) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('The selected service is invalid.'),
            ]);
        }

        if (! $destinationService->is_active) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('This queue service is not accepting tickets.'),
            ]);
        }

        $sameService = $destinationService->id === $ticket->queue_service_id;
        $sameCounter = $destinationCounter === null || $destinationCounter->id === $fromCounter->id;

        if ($sameService && $sameCounter) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('Choose a different service or counter to transfer to.'),
            ]);
        }

        if ($destinationCounter === null) {
            return;
        }

        if ($destinationCounter->team_id !== $fromCounter->team_id) {
            throw ValidationException::withMessages([
                'counter_id' => __('The selected counter is invalid.'),
            ]);
        }

        $supportsService = $destinationCounter->services()
            ->whereKey($destinationService->id)
            ->exists();

        if (! $supportsService) {
            throw ValidationException::withMessages([
                'counter_id' => __('That counter does not support the selected service.'),
            ]);
        }
    }
}
