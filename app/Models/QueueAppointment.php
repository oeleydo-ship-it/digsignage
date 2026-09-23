<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\QueueAppointmentCheckInSource;
use App\Enums\QueueAppointmentStatus;
use Database\Factories\QueueAppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $queue_service_id
 * @property int|null $location_id
 * @property int|null $queue_ticket_id
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property Carbon $scheduled_at
 * @property string $reference
 * @property QueueAppointmentStatus $status
 * @property QueueAppointmentCheckInSource|null $check_in_source
 * @property Carbon|null $checked_in_at
 * @property-read QueueService $service
 * @property-read Location|null $location
 * @property-read QueueTicket|null $ticket
 */
#[Fillable([
    'team_id', 'queue_service_id', 'location_id', 'queue_ticket_id',
    'customer_name', 'customer_phone', 'customer_email', 'scheduled_at',
    'reference', 'status', 'check_in_source', 'checked_in_at',
])]
class QueueAppointment extends Model
{
    /** @use HasFactory<QueueAppointmentFactory> */
    use BelongsToTeam, HasFactory;

    /** @return BelongsTo<QueueService, $this> */
    /** @return BelongsTo<QueueService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(QueueService::class, 'queue_service_id');
    }

    /** @return BelongsTo<Location, $this> */
    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<QueueTicket, $this> */
    /** @return BelongsTo<QueueTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'status' => QueueAppointmentStatus::class,
            'check_in_source' => QueueAppointmentCheckInSource::class,
        ];
    }
}
