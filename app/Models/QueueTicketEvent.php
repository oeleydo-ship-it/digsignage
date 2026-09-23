<?php

namespace App\Models;

use App\Actions\Queue\DispatchQueueCustomerNotification;
use App\Concerns\BelongsToTeam;
use App\Enums\QueueNotificationEvent;
use App\Enums\QueueTicketEventType;
use Database\Factories\QueueTicketEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $team_id
 * @property int $queue_ticket_id
 * @property int|null $user_id
 * @property QueueTicketEventType $type
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QueueTicket $ticket
 * @property-read User|null $user
 */
#[Fillable([
    'team_id',
    'queue_ticket_id',
    'user_id',
    'type',
    'payload',
])]
class QueueTicketEvent extends Model
{
    /** @use HasFactory<QueueTicketEventFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Ticket this journey event belongs to.
     *
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    /**
     * Actor who caused the event, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (QueueTicketEvent $event): void {
            $notification = match ($event->type) {
                QueueTicketEventType::Created => QueueNotificationEvent::TicketCreated,
                QueueTicketEventType::Transferred => QueueNotificationEvent::TicketTransferred,
                QueueTicketEventType::StatusChanged => ($event->payload['to'] ?? null) === 'called'
                    ? QueueNotificationEvent::TicketCalled
                    : null,
            };

            if ($notification === null) {
                return;
            }

            DB::afterCommit(function () use ($event, $notification): void {
                $ticket = QueueTicket::query()->find($event->queue_ticket_id);

                if ($ticket !== null) {
                    app(DispatchQueueCustomerNotification::class)
                        ->forTicket($ticket, $notification, 'event:'.$event->id);
                }
            });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QueueTicketEventType::class,
            'payload' => 'array',
        ];
    }
}
