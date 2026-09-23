<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\EmergencyTargetType;
use Database\Factories\EmergencyTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $emergency_id
 * @property EmergencyTargetType $target_type
 * @property int|null $screen_id
 * @property int|null $location_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Emergency $emergency
 * @property-read Screen|null $screen
 * @property-read Location|null $location
 */
#[Fillable([
    'team_id',
    'emergency_id',
    'target_type',
    'screen_id',
    'location_id',
])]
class EmergencyTarget extends Model
{
    /** @use HasFactory<EmergencyTargetFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Emergency, $this>
     */
    public function emergency(): BelongsTo
    {
        return $this->belongsTo(Emergency::class);
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'target_type' => EmergencyTargetType::class,
        ];
    }
}
