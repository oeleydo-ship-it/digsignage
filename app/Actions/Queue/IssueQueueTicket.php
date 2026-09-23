<?php

namespace App\Actions\Queue;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\QueueTicketEventType;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Enums\WebhookEvent;
use App\Models\QueuePriority;
use App\Models\QueueService;
use App\Models\QueueTicket;
use App\Models\QueueTicketEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\QueueAuditSnapshot;
use App\Support\QueueTicketNumbering;
use App\Support\QueueTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IssueQueueTicket
{
    public function __construct(
        protected RecomputeQueuePositions $recomputeQueuePositions,
        protected EnsureDefaultQueuePriorities $ensureDefaultQueuePriorities,
        protected RecordOrganizationAudit $recordOrganizationAudit,
        protected DispatchQueueTicketWebhook $dispatchQueueTicketWebhook,
    ) {
        //
    }

    /**
     * Issue the next ticket for a service, locking the numbering cursor.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, QueueService $service, array $attributes = [], ?User $actor = null): QueueTicket
    {
        if ($service->team_id !== $team->id) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('The selected service is invalid.'),
            ]);
        }

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'queue_service_id' => __('This queue service is not accepting tickets.'),
            ]);
        }

        $idempotencyKey = $this->idempotencyKey($attributes['idempotency_key'] ?? null);

        return QueueTransaction::run(function () use ($team, $service, $attributes, $actor, $idempotencyKey) {
            /** @var QueueService $service */
            $service = QueueService::query()
                ->whereKey($service->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $service->is_active) {
                throw ValidationException::withMessages([
                    'queue_service_id' => __('This queue service is not accepting tickets.'),
                ]);
            }

            if ($idempotencyKey !== null) {
                $existing = QueueTicket::query()
                    ->where('team_id', $team->id)
                    ->where('queue_service_id', $service->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $at = now();
            $period = QueueTicketNumbering::periodKey($service->numbering_reset, $at);

            if ($service->sequence_period !== $period) {
                $service->next_sequence = 1;
                $service->sequence_period = $period;
            }

            $source = $this->source($attributes['source'] ?? QueueTicketSource::Staff);
            [$queuePriorityId, $priority] = $this->resolvePriority($team, $service, $attributes);

            $attempts = 0;
            $ticket = null;

            while ($attempts < 25) {
                $sequence = max(1, (int) $service->next_sequence);
                $number = QueueTicketNumbering::format($service->ticket_prefix, $sequence);

                try {
                    $ticket = QueueTicket::query()->create([
                        'team_id' => $team->id,
                        'queue_service_id' => $service->id,
                        'location_id' => $service->location_id,
                        'number' => $number,
                        'sequence' => $sequence,
                        'numbering_period' => $period,
                        'status' => QueueTicketStatus::Waiting,
                        'customer_name' => $attributes['customer_name'] ?? null,
                        'customer_phone' => $attributes['customer_phone'] ?? null,
                        'customer_email' => $attributes['customer_email'] ?? null,
                        'priority' => $priority,
                        'queue_priority_id' => $queuePriorityId,
                        'queue_position' => 0,
                        'source' => $source,
                        'public_token' => $attributes['public_token']
                            ?? ($source === QueueTicketSource::Qr ? Str::lower(Str::random(48)) : null),
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    $service->forceFill([
                        'next_sequence' => $sequence + 1,
                        'last_issued' => $sequence,
                        'sequence_period' => $period,
                    ])->save();

                    break;
                } catch (UniqueConstraintViolationException) {
                    $service->next_sequence = $sequence + 1;
                    $attempts++;
                }
            }

            if ($ticket === null) {
                throw ValidationException::withMessages([
                    'number' => __('A ticket number could not be issued. Please try again.'),
                ]);
            }

            QueueTicketEvent::query()->create([
                'team_id' => $team->id,
                'queue_ticket_id' => $ticket->id,
                'user_id' => $actor?->id,
                'type' => QueueTicketEventType::Created,
                'payload' => [
                    'status' => QueueTicketStatus::Waiting->value,
                    'number' => $ticket->number,
                    'source' => $source->value,
                ],
            ]);

            $this->recomputeQueuePositions->handle($service);

            $ticket = $ticket->refresh();

            $this->recordOrganizationAudit->handle(
                $team,
                AuditAction::QueueTicketCreated,
                $actor,
                'queue_ticket',
                $ticket->id,
                null,
                QueueAuditSnapshot::ticket($ticket),
            );
            $this->dispatchQueueTicketWebhook->handle($ticket, WebhookEvent::TicketCreated);

            return $ticket;
        });
    }

    protected function idempotencyKey(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return hash('sha256', trim($value));
    }

    protected function source(mixed $value): QueueTicketSource
    {
        if ($value instanceof QueueTicketSource) {
            return $value;
        }

        if (is_string($value)) {
            $source = QueueTicketSource::tryFrom($value);

            if ($source !== null) {
                return $source;
            }
        }

        throw new RuntimeException('Invalid queue ticket source.');
    }

    /**
     * Copy the selected priority weight onto the ticket; keep a FK to the definition.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: int|null, 1: int}
     */
    protected function resolvePriority(Team $team, QueueService $service, array $attributes): array
    {
        $this->ensureDefaultQueuePriorities->handle($team);

        $priorityId = $attributes['queue_priority_id'] ?? null;

        if ($priorityId !== null && $priorityId !== '') {
            $definition = QueuePriority::query()
                ->where('team_id', $team->id)
                ->where('is_active', true)
                ->whereKey($priorityId)
                ->first();

            if ($definition === null) {
                throw ValidationException::withMessages([
                    'queue_priority_id' => __('The selected priority is invalid.'),
                ]);
            }

            return [$definition->id, $definition->weight];
        }

        if (array_key_exists('priority', $attributes)) {
            $weight = (int) $attributes['priority'];
            $match = QueuePriority::query()
                ->where('team_id', $team->id)
                ->where('weight', $weight)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();

            return [$match?->id, $weight];
        }

        $normal = QueuePriority::query()
            ->where('team_id', $team->id)
            ->where('code', QueuePriority::NORMAL_CODE)
            ->where('is_active', true)
            ->first();

        if ($normal !== null) {
            return [$normal->id, $normal->weight];
        }

        return [null, $service->default_priority];
    }
}
