<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\QueuePriorityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $code
 * @property int $weight
 * @property string|null $color
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, QueueTicket> $tickets
 */
#[Fillable([
    'team_id',
    'name',
    'code',
    'weight',
    'color',
    'is_active',
    'sort_order',
])]
class QueuePriority extends Model
{
    /** @use HasFactory<QueuePriorityFactory> */
    use BelongsToTeam, HasFactory;

    public const NORMAL_CODE = 'normal';

    /**
     * Tickets issued with this priority definition.
     *
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * Seeded levels for a new team. Administrators may edit or delete these.
     *
     * @return list<array{name: string, code: string, weight: int, color: string, sort_order: int}>
     */
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'Normal', 'code' => self::NORMAL_CODE, 'weight' => 0, 'color' => '#64748b', 'sort_order' => 0],
            ['name' => 'Appointment', 'code' => 'appointment', 'weight' => 10, 'color' => '#2563eb', 'sort_order' => 1],
            ['name' => 'Senior', 'code' => 'senior', 'weight' => 20, 'color' => '#7c3aed', 'sort_order' => 2],
            ['name' => 'VIP', 'code' => 'vip', 'weight' => 30, 'color' => '#ca8a04', 'sort_order' => 3],
            ['name' => 'Emergency', 'code' => 'emergency', 'weight' => 100, 'color' => '#dc2626', 'sort_order' => 4],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
