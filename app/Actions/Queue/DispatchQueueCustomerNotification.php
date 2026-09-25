<?php

namespace App\Actions\Queue;

use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Jobs\SendQueueCustomerNotification;
use App\Models\QueueAppointment;
use App\Models\QueueNotificationDelivery;
use App\Models\QueueNotificationRule;
use App\Models\QueuePushSubscription;
use App\Models\QueueTicket;
use App\Support\QueueNotificationChannels;
use App\Support\QueueNotificationTemplates;

class DispatchQueueCustomerNotification
{
    public function __construct(
        protected QueueNotificationTemplates $templates,
        protected QueueNotificationChannels $channels,
    ) {}

    public function forTicket(QueueTicket $ticket, QueueNotificationEvent $event, string $occurrence = 'once'): void
    {
        $ticket->loadMissing(['service:id,name', 'counter:id,name', 'location:id,name', 'team:id,name']);
        $values = [
            'ticket' => $ticket->number,
            'name' => $ticket->customer_name,
            'service' => $ticket->service->name,
            'counter' => $ticket->counter_id === null ? __('the counter') : $ticket->counter->name,
            'position' => $ticket->queue_position,
            'location' => $ticket->location?->name,
            'link' => filled($ticket->public_token) ? route('queue.virtual.ticket', $ticket->public_token) : null,
            'team' => $ticket->team->name,
        ];
        ['subject' => $subject, 'body' => $body] = $this->templates->render($ticket->team_id, $event, $values);
        $hasPush = filled($ticket->public_token)
            && QueuePushSubscription::query()->where('queue_ticket_id', $ticket->id)->exists();

        $this->dispatchRules(
            teamId: $ticket->team_id,
            event: $event,
            occurrence: 'ticket:'.$ticket->id.':'.$occurrence,
            subject: $subject,
            body: $body,
            data: [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->number,
                'service' => $ticket->service->name,
                'counter' => $ticket->counter?->name,
                'queue_position' => $ticket->queue_position,
            ],
            destinations: [
                QueueNotificationChannel::Email->value => $ticket->customer_email,
                QueueNotificationChannel::Sms->value => $ticket->customer_phone,
                QueueNotificationChannel::WhatsApp->value => $ticket->customer_phone,
                QueueNotificationChannel::Push->value => $hasPush ? $ticket->public_token : null,
            ],
            ticketId: $ticket->id,
        );
    }

    public function forAppointment(QueueAppointment $appointment): void
    {
        $appointment->loadMissing(['service:id,name', 'location:id,name']);
        ['subject' => $subject, 'body' => $body] = $this->templates->render($appointment->team_id, QueueNotificationEvent::AppointmentApproaching, [
            'name' => $appointment->customer_name,
            'service' => $appointment->service->name,
            'location' => $appointment->location?->name,
            'time' => $appointment->scheduled_at->format('M j, Y g:i A'),
            'reference' => $appointment->reference,
        ]);

        $this->dispatchRules(
            teamId: $appointment->team_id,
            event: QueueNotificationEvent::AppointmentApproaching,
            occurrence: 'appointment:'.$appointment->id.':'.$appointment->scheduled_at->timestamp,
            subject: $subject,
            body: $body,
            data: [
                'appointment_id' => $appointment->id,
                'reference' => $appointment->reference,
                'service' => $appointment->service->name,
                'location' => $appointment->location?->name,
                'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
            ],
            destinations: [
                QueueNotificationChannel::Email->value => $appointment->customer_email,
                QueueNotificationChannel::Sms->value => $appointment->customer_phone,
                QueueNotificationChannel::WhatsApp->value => $appointment->customer_phone,
                // Appointments have no page for a browser to subscribe on.
                QueueNotificationChannel::Push->value => null,
            ],
            appointmentId: $appointment->id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $destinations
     */
    protected function dispatchRules(int $teamId, QueueNotificationEvent $event, string $occurrence, string $subject, string $body, array $data, array $destinations, ?int $ticketId = null, ?int $appointmentId = null): void
    {
        $rules = QueueNotificationRule::query()
            ->where('team_id', $teamId)
            ->where('event', $event->value)
            ->where('is_enabled', true)
            ->get();

        foreach ($rules as $rule) {
            $destination = $destinations[$rule->channel->value] ?? null;
            $skip = match (true) {
                ! $this->channels->canSend($teamId, $rule->channel) => __(':channel is not set up.', ['channel' => $rule->channel->label()]),
                blank($destination) && $rule->channel === QueueNotificationChannel::Push => __('The customer has not turned on notifications.'),
                blank($destination) => __('Customer destination is unavailable.'),
                default => null,
            };
            $dedupeKey = hash('sha256', $teamId.'|'.$event->value.'|'.$rule->channel->value.'|'.$occurrence);
            $delivery = QueueNotificationDelivery::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'team_id' => $teamId,
                    'queue_notification_rule_id' => $rule->id,
                    'queue_ticket_id' => $ticketId,
                    'queue_appointment_id' => $appointmentId,
                    'event' => $event,
                    'channel' => $rule->channel,
                    'destination' => $destination,
                    'status' => $skip === null ? 'pending' : 'skipped',
                    'payload' => ['subject' => $subject, 'body' => $body, 'data' => $data],
                    'error' => $skip,
                ],
            );

            if ($delivery->wasRecentlyCreated && $delivery->status === 'pending') {
                SendQueueCustomerNotification::dispatch($delivery->id)->afterCommit();
            }
        }
    }
}
