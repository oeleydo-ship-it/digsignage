<?php

namespace App\Actions\Partner;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Team;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class DispatchPartnerWebhook
{
    /**
     * Queue webhook deliveries for every endpoint subscribed to the event.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, WebhookEvent $event, array $data): void
    {
        $payload = [
            'id' => null,
            'event' => $event->value,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        WebhookEndpoint::query()
            ->forTeam($team)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event))
            ->each(function (WebhookEndpoint $endpoint) use ($team, $event, $payload) {
                $delivery = WebhookDelivery::query()->create([
                    'team_id' => $team->id,
                    'webhook_endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'payload' => $payload,
                    'status' => WebhookDeliveryStatus::Pending,
                ]);

                $payload['id'] = $delivery->uuid;
                $delivery->forceFill(['payload' => $payload])->save();

                DeliverWebhook::dispatch($delivery)->afterCommit();
            });
    }
}
