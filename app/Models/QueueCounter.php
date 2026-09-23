<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\QueueCounterStatus;
use App\Enums\QueueTicketStatus;
use Database\Factories\QueueCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $location_id
 * @property string $name
 * @property string $code
 * @property QueueCounterStatus $status
 * @property int|null $assigned_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $location
 * @property-read User|null $assignedUser
 * @property-read Collection<int, QueueService> $services
 * @property-read Collection<int, QueueTicket> $tickets
 */
#[Fillable([
    'team_id',
    'location_id',
    'name',
    'code',
    'status',
    'assigned_user_id',
])]
class QueueCounter extends Model
{
    /** @use HasFactory<QueueCounterFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Optional location this desk belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Staff member assigned to this desk, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Services this counter can call.
     *
     * @return BelongsToMany<QueueService, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(QueueService::class, 'queue_counter_service', 'counter_id', 'queue_service_id');
    }

    /**
     * Tickets that have been assigned to this counter.
     *
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class, 'counter_id');
    }

    /**
     * Ticket currently called or being served at this desk.
     */
    public function currentTicket(): ?QueueTicket
    {
        return $this->tickets()
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Serving])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueCounterStatus::class,
        ];
    }
}
