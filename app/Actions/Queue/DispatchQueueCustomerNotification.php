<?php

namespace App\Actions\Queue;

use App\Enums\QueueNotificationChannel;
use App\Enums\QueueNotificationEvent;
use App\Jobs\SendQueueCustomerNotification;
use App\Models\QueueAppointment;
use App\Models\QueueNotificationDelivery;
use App\Models\QueueNotificationRule;
use App\Models\QueueTicket;

class DispatchQueueCustomerNotification
{
    public function forTicket(QueueTicket $ticket, QueueNotificationEvent $event, string $occurrence = 'once'): void
    {
        $ticket->loadMissing(['service:id,name', 'counter:id,name']);
        [$subject, $body] = $this->ticketCopy($ticket, $event);
        $data = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->number,
            'service' => $ticket->service->name,
            'counter' => $ticket->counter?->name,
            'queue_position' => $ticket->queue_position,
        ];

        $this->dispatchRules(
            teamId: $ticket->team_id,
            event: $event,
            occurrence: 'ticket:'.$ticket->id.':'.$occurrence,
            subject: $subject,
            body: $body,
            data: $data,
            destinations: [
                QueueNotificationChannel::Email->value => $ticket->customer_email,
                QueueNotificationChannel::Sms->value => $ticket->customer_phone,
                QueueNotificationChannel::WhatsApp->value => $ticket->customer_phone,
                QueueNotificationChannel::Push->value => $ticket->public_token,
            ],
            ticketId: $ticket->id,
        );
    }

    public function forAppointment(QueueAppointment $appointment): void
    {
        $appointment->loadMissing(['service:id,name', 'location:id,name']);
        $subject = 'Appointment reminder';
        $body = __('Your :service appointment is at :time.', [
            'service' => $appointment->service->name,
            'time' => $appointment->scheduled_at->format('M j, Y g:i A'),
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
                QueueNotificationChannel::Push->value => $appointment->reference,
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
                    'status' => filled($destination) ? 'pending' : 'skipped',
                    'payload' => ['subject' => $subject, 'body' => $body, 'data' => $data],
                    'error' => filled($destination) ? null : 'Customer destination is unavailable.',
                ],
            );

            if ($delivery->wasRecentlyCreated && $delivery->status === 'pending') {
                SendQueueCustomerNotification::dispatch($delivery->id)->afterCommit();
            }
        }
    }

    /** @return array{string, string} */
    protected function ticketCopy(QueueTicket $ticket, QueueNotificationEvent $event): array
    {
        $subject = 'Queue update for '.$ticket->number;
        $counterName = $ticket->counter_id === null ? __('the counter') : $ticket->counter->name;
        $body = match ($event) {
            QueueNotificationEvent::TicketCreated => __('Ticket :ticket was created for :service.', ['ticket' => $ticket->number, 'service' => $ticket->service->name]),
            QueueNotificationEvent::FiveAhead => __('There are 5 customers ahead of ticket :ticket.', ['ticket' => $ticket->number]),
            QueueNotificationEvent::ThreeAhead => __('There are 3 customers ahead of ticket :ticket.', ['ticket' => $ticket->number]),
            QueueNotificationEvent::CustomerNext => __('Ticket :ticket is next.', ['ticket' => $ticket->number]),
            QueueNotificationEvent::TicketCalled => __('Ticket :ticket has been called. Please proceed to :counter.', ['ticket' => $ticket->number, 'counter' => $counterName]),
            QueueNotificationEvent::TicketTransferred => __('Ticket :ticket was transferred to :service.', ['ticket' => $ticket->number, 'service' => $ticket->service->name]),
            QueueNotificationEvent::AppointmentApproaching => '',
        };

        return [$subject, $body];
    }
}
