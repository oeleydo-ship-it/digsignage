<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\WebhookEvent;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueAuditSnapshot;
use App\Support\QueueTransaction;
use Illuminate\Validation\ValidationException;

class CancelQueueTicket
{
    public function __construct(
        protected RecomputeQueuePositions $recomputeQueuePositions,
        protected RecordOrganizationAudit $recordOrganizationAudit,
        protected DispatchQueueTicketWebhook $dispatchQueueTicketWebhook,
    ) {
        //
    }

    /**
     * Cancel a waiting ticket and recompute remaining positions.
     */
    public function handle(QueueTicket $ticket, ?User $actor = null): QueueTicket
    {
        if ($ticket->status !== QueueTicketStatus::Waiting) {
            throw ValidationException::withMessages([
                'status' => __('Only waiting tickets can be cancelled.'),
            ]);
        }

        return QueueTransaction::run(function () use ($ticket, $actor) {
            $ticket = QueueTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->status !== QueueTicketStatus::Waiting) {
                throw ValidationException::withMessages([
                    'status' => __('Only waiting tickets can be cancelled.'),
                ]);
            }

            $before = QueueAuditSnapshot::ticket($ticket);
            $from = $ticket->status;
            $now = now();

            $ticket->forceFill([
                'status' => QueueTicketStatus::Cancelled,
                'queue_position' => null,
                'waiting_duration_seconds' => $ticket->created_at !== null
                    ? (int) $ticket->created_at->diffInSeconds($now)
                    : 0,
            ])->save();

            QueueTicketEvent::query()->create([
                'team_id' => $ticket->team_id,
                'queue_ticket_id' => $ticket->id,
                'user_id' => $actor?->id,
                'type' => QueueTicketEventType::StatusChanged,
                'payload' => [
                    'from' => $from->value,
                    'to' => QueueTicketStatus::Cancelled->value,
                ],
            ]);

            $this->recomputeQueuePositions->handle($ticket->service);
            $ticket = $ticket->refresh();

            $this->recordOrganizationAudit->handle(
                Team::query()->findOrFail($ticket->team_id),
                AuditAction::QueueTicketCancelled,
                $actor,
                'queue_ticket',
                $ticket->id,
                $before,
                QueueAuditSnapshot::ticket($ticket),
            );
            $this->dispatchQueueTicketWebhook->handle($ticket, WebhookEvent::TicketCancelled);

            return $ticket;
        });
    }
}
