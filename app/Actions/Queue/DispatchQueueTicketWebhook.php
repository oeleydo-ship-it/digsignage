<?php

namespace App\Actions\Queue;

use App\Actions\Partner\DispatchPartnerWebhook;
use App\Enums\PlanFeature;
use App\Enums\WebhookEvent;
use App\Models\QueueTicket;
use App\Models\Team;
use App\Support\TeamQuota;

class DispatchQueueTicketWebhook
{
    public function __construct(
        protected DispatchPartnerWebhook $dispatchPartnerWebhook,
        protected TeamQuota $quota,
    ) {
        //
    }

    /**
     * Create subscribed delivery records inside the ticket transaction.
     * The delivery jobs themselves are held until that transaction commits.
     *
     * @param  array<string, mixed>  $context
     */
    public function handle(QueueTicket $ticket, WebhookEvent $event, array $context = []): void
    {
        $team = Team::query()->findOrFail($ticket->team_id);

        if (! $this->quota->allowsFeature($team, PlanFeature::Webhooks)) {
            return;
        }

        $this->dispatchPartnerWebhook->handle($team, $event, [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'queue_service_id' => $ticket->queue_service_id,
            'location_id' => $ticket->location_id,
            'counter_id' => $ticket->counter_id,
            'assigned_user_id' => $ticket->assigned_user_id,
            'queue_priority_id' => $ticket->queue_priority_id,
            'priority' => $ticket->priority,
            'queue_position' => $ticket->queue_position,
            'source' => $ticket->source->value,
            'called_at' => $ticket->called_at?->toIso8601String(),
            'service_started_at' => $ticket->service_started_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            ...$context,
        ]);
    }
}
