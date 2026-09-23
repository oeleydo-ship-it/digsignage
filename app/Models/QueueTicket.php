<?php

namespace App\Models;

use App\Actions\Queue\DispatchQueueCustomerNotification;
use App\Concerns\BelongsToTeam;
use App\Enums\QueueNotificationEvent;
use App\Enums\QueueTicketSource;
use App\Enums\QueueTicketStatus;
use App\Events\QueueUpdated;
use App\Support\PlayerManifestCache;
use Database\Factories\QueueTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $team_id
 * @property int $queue_service_id
 * @property int|null $location_id
 * @property string $number
 * @property int $sequence
 * @property string $numbering_period
 * @property QueueTicketStatus $status
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property int $priority
 * @property int|null $queue_priority_id
 * @property int|null $queue_position
 * @property Carbon|null $called_at
 * @property Carbon|null $service_started_at
 * @property Carbon|null $completed_at
 * @property int|null $counter_id
 * @property int|null $assigned_user_id
 * @property int|null $waiting_duration_seconds
 * @property int|null $serving_duration_seconds
 * @property QueueTicketSource $source
 * @property string|null $public_token
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QueueService $service
 * @property-read QueuePriority|null $queuePriority
 * @property-read Location|null $location
 * @property-read User|null $assignedUser
 * @property-read QueueCounter|null $counter
 * @property-read Collection<int, QueueTicketEvent> $events
 */
#[Fillable([
    'team_id',
    'queue_service_id',
    'location_id',
    'number',
    'sequence',
    'numbering_period',
    'status',
    'customer_name',
    'customer_phone',
    'customer_email',
    'priority',
    'queue_priority_id',
    'queue_position',
    'called_at',
    'service_started_at',
    'completed_at',
    'counter_id',
    'assigned_user_id',
    'waiting_duration_seconds',
    'serving_duration_seconds',
    'source',
    'public_token',
    'idempotency_key',
])]
#[Hidden(['public_token', 'idempotency_key'])]
class QueueTicket extends Model
{
    /** @use HasFactory<QueueTicketFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Service this ticket belongs to.
     *
     * @return BelongsTo<QueueService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(QueueService::class, 'queue_service_id');
    }

    /**
     * Priority definition copied onto the ticket at issue time.
     *
     * @return BelongsTo<QueuePriority, $this>
     */
    public function queuePriority(): BelongsTo
    {
        return $this->belongsTo(QueuePriority::class);
    }

    /**
     * Location copied from the service at issue time.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Staff member assigned to this ticket (Phase 5+ call-next).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Counter currently or last assigned to this ticket.
     *
     * @return BelongsTo<QueueCounter, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(QueueCounter::class, 'counter_id');
    }

    /**
     * Journey events for this ticket.
     *
     * @return HasMany<QueueTicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(QueueTicketEvent::class);
    }

    /**
     * Seconds spent waiting, stored when the wait ends or live if still waiting.
     */
    public function waitingDurationSeconds(): ?int
    {
        if ($this->waiting_duration_seconds !== null) {
            return $this->waiting_duration_seconds;
        }

        if ($this->status === QueueTicketStatus::Waiting && $this->created_at !== null) {
            return (int) $this->created_at->diffInSeconds(now());
        }

        return null;
    }

    /**
     * Seconds spent serving, stored when service ends or live if currently serving.
     */
    public function servingDurationSeconds(): ?int
    {
        if ($this->serving_duration_seconds !== null) {
            return $this->serving_duration_seconds;
        }

        if ($this->status === QueueTicketStatus::Serving && $this->service_started_at !== null) {
            return (int) $this->service_started_at->diffInSeconds(now());
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueTicketStatus::class,
            'source' => QueueTicketSource::class,
            'sequence' => 'integer',
            'priority' => 'integer',
            'queue_position' => 'integer',
            'called_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'waiting_duration_seconds' => 'integer',
            'serving_duration_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (QueueTicket $ticket): void {
            if ($ticket->wasRecentlyCreated || $ticket->wasChanged([
                'status',
                'queue_position',
                'queue_service_id',
                'counter_id',
                'called_at',
            ])) {
                // Invalidate before broadcasting. Otherwise a player can fetch
                // the old manifest in response to this event and keep it.
                PlayerManifestCache::bumpTeam($ticket->team_id);
                if (DB::transactionLevel() > 0) {
                    DB::afterCommit(fn () => PlayerManifestCache::bumpTeam($ticket->team_id));
                }

                event($ticket->queueUpdatedEvent($ticket->queue_service_id));

                if ($ticket->wasChanged('queue_service_id')) {
                    event($ticket->queueUpdatedEvent((int) $ticket->getOriginal('queue_service_id')));
                }
            }

            if ($ticket->wasChanged('queue_position') && $ticket->status === QueueTicketStatus::Waiting) {
                $notification = match ($ticket->queue_position) {
                    6 => QueueNotificationEvent::FiveAhead,
                    4 => QueueNotificationEvent::ThreeAhead,
                    2 => QueueNotificationEvent::CustomerNext,
                    default => null,
                };

                if ($notification !== null) {
                    DB::afterCommit(function () use ($ticket, $notification): void {
                        $fresh = QueueTicket::query()->find($ticket->id);
                        if ($fresh !== null) {
                            app(DispatchQueueCustomerNotification::class)
                                ->forTicket($fresh, $notification, 'position:'.$ticket->queue_position);
                        }
                    });
                }
            }
        });

        static::deleted(function (QueueTicket $ticket): void {
            PlayerManifestCache::bumpTeam($ticket->team_id);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(fn () => PlayerManifestCache::bumpTeam($ticket->team_id));
            }
            event($ticket->queueUpdatedEvent($ticket->queue_service_id));
        });
    }

    protected function queueUpdatedEvent(int $serviceId): QueueUpdated
    {
        return new QueueUpdated(
            teamId: $this->team_id,
            serviceId: $serviceId,
            ticketId: $this->id,
            ticketNumber: $this->number,
            status: $this->status->value,
            counterId: $this->counter_id,
            locationId: $this->location_id,
            counterName: $this->counter_id !== null ? $this->counter()->value('name') : null,
            calledAt: $this->called_at?->toIso8601String(),
        );
    }
}
