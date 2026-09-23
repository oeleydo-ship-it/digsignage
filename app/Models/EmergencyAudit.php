<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\EmergencyAuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $emergency_id
 * @property int|null $user_id
 * @property int|null $screen_id
 * @property EmergencyAuditAction $action
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Emergency $emergency
 * @property-read User|null $user
 * @property-read Screen|null $screen
 */
#[Fillable([
    'team_id',
    'emergency_id',
    'user_id',
    'screen_id',
    'action',
    'payload',
])]
class EmergencyAudit extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Emergency, $this>
     */
    public function emergency(): BelongsTo
    {
        return $this->belongsTo(Emergency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'action' => EmergencyAuditAction::class,
            'payload' => 'array',
        ];
    }
}
