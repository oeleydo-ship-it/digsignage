<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use Database\Factories\DeviceCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $screen_id
 * @property int|null $issued_by
 * @property DeviceCommandType $command
 * @property array<string, mixed>|null $payload
 * @property DeviceCommandStatus $status
 * @property array<string, mixed>|null $result
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Screen $screen
 * @property-read User|null $issuer
 */
#[Fillable([
    'team_id',
    'screen_id',
    'issued_by',
    'command',
    'payload',
    'status',
    'result',
    'sent_at',
    'acknowledged_at',
    'completed_at',
    'expires_at',
])]
class DeviceCommand extends Model
{
    /** @use HasFactory<DeviceCommandFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Payload delivered to the player over Reverb or REST.
     *
     * @return array<string, mixed>
     */
    public function playerPayload(): array
    {
        return [
            'command_id' => $this->id,
            'screen_id' => $this->screen_id,
            'command' => $this->command->value,
            'payload' => $this->payload ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'status' => $this->status->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'command' => DeviceCommandType::class,
            'status' => DeviceCommandStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
