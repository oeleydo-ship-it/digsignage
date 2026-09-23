<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\EmergencyDeliveryStatus;
use Database\Factories\EmergencyDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $emergency_id
 * @property int $screen_id
 * @property int|null $device_command_id
 * @property EmergencyDeliveryStatus $status
 * @property array<string, mixed>|null $result
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Emergency $emergency
 * @property-read Screen $screen
 * @property-read DeviceCommand|null $deviceCommand
 */
#[Fillable([
    'team_id',
    'emergency_id',
    'screen_id',
    'device_command_id',
    'status',
    'result',
    'sent_at',
    'acknowledged_at',
    'completed_at',
])]
class EmergencyDelivery extends Model
{
    /** @use HasFactory<EmergencyDeliveryFactory> */
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
     * @return BelongsTo<DeviceCommand, $this>
     */
    public function deviceCommand(): BelongsTo
    {
        return $this->belongsTo(DeviceCommand::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => EmergencyDeliveryStatus::class,
            'result' => 'array',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
