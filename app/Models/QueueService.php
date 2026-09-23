<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\QueueNumberingReset;
use App\Enums\QueueStrategy;
use App\Support\QueueOpeningHours;
use App\Support\QueueTicketNumbering;
use Database\Factories\QueueServiceFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Average service duration is stored in **seconds** (UI may collect minutes).
 *
 * Ticket issuance is Phase 3. next_sequence / last_issued / sequence_period
 * hold the current numbering cursor and the period key it applies to.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $location_id
 * @property string $name
 * @property string $code
 * @property string $ticket_prefix
 * @property string|null $description
 * @property array<string, array{open: string, close: string, closed: bool}>|null $opening_hours
 * @property int $average_service_duration_seconds
 * @property int|null $max_queue_capacity
 * @property QueueNumberingReset $numbering_reset
 * @property int $next_sequence
 * @property int|null $last_issued
 * @property string|null $sequence_period
 * @property array<int, mixed>|null $priority_rules
 * @property int $default_priority
 * @property QueueStrategy $queue_strategy
 * @property array<string, mixed>|null $starvation
 * @property string $display_color
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $location
 * @property-read Collection<int, QueueTicket> $tickets
 * @property-read Collection<int, QueueCounter> $counters
 */
#[Fillable([
    'team_id',
    'location_id',
    'name',
    'code',
    'ticket_prefix',
    'description',
    'opening_hours',
    'average_service_duration_seconds',
    'max_queue_capacity',
    'numbering_reset',
    'next_sequence',
    'last_issued',
    'sequence_period',
    'priority_rules',
    'default_priority',
    'queue_strategy',
    'starvation',
    'display_color',
    'is_active',
])]
class QueueService extends Model
{
    /** @use HasFactory<QueueServiceFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the optional location this queue belongs to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Issued tickets for this service.
     *
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * Counters that can serve this queue.
     *
     * @return BelongsToMany<QueueCounter, $this>
     */
    public function counters(): BelongsToMany
    {
        return $this->belongsToMany(QueueCounter::class, 'queue_counter_service', 'queue_service_id', 'counter_id');
    }

    /**
     * Preview the next ticket label (prefix + padded sequence) without issuing a ticket.
     */
    public function nextTicketNumber(?DateTimeInterface $at = null): string
    {
        return QueueTicketNumbering::format($this->ticket_prefix, $this->previewNextSequence($at));
    }

    /**
     * Sequence that would be used if a ticket were issued at $at.
     *
     * When the numbering period has rolled over, the preview is 1 even if
     * next_sequence is still the previous period's cursor.
     */
    public function previewNextSequence(?DateTimeInterface $at = null): int
    {
        $at ??= now();
        $period = QueueTicketNumbering::periodKey($this->numbering_reset, $at);

        if ($this->numbering_reset !== QueueNumberingReset::Never && $this->sequence_period !== $period) {
            return 1;
        }

        return max(1, $this->next_sequence);
    }

    /**
     * Period key currently in effect for this service's reset policy.
     */
    public function currentPeriodKey(?DateTimeInterface $at = null): string
    {
        return QueueTicketNumbering::periodKey($this->numbering_reset, $at ?? now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'priority_rules' => 'array',
            'numbering_reset' => QueueNumberingReset::class,
            'queue_strategy' => QueueStrategy::class,
            'starvation' => 'array',
            'average_service_duration_seconds' => 'integer',
            'max_queue_capacity' => 'integer',
            'next_sequence' => 'integer',
            'last_issued' => 'integer',
            'default_priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Apply defaults before first persist.
     */
    protected static function booted(): void
    {
        static::creating(function (QueueService $service): void {
            if ($service->opening_hours === null) {
                $service->opening_hours = QueueOpeningHours::defaults();
            }

            if ($service->priority_rules === null) {
                $service->priority_rules = [];
            }

            if ($service->sequence_period === null) {
                $service->sequence_period = QueueTicketNumbering::periodKey(
                    $service->numbering_reset,
                    now(),
                );
            }
        });
    }
}
