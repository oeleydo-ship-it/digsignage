<?php

namespace App\Models;

use App\Support\RegistrationCode;
use Database\Factories\DeviceRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code_hash
 * @property int|null $team_id
 * @property int|null $screen_id
 * @property int|null $claimed_by_user_id
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $player_ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read Screen|null $screen
 * @property-read User|null $claimedBy
 */
#[Fillable([
    'code_hash',
    'team_id',
    'screen_id',
    'claimed_by_user_id',
    'expires_at',
    'consumed_at',
    'player_ip',
    'user_agent',
])]
class DeviceRegistration extends Model
{
    /** @use HasFactory<DeviceRegistrationFactory> */
    use HasFactory;

    /**
     * Get the team that claimed this registration.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the paired screen.
     *
     * @return BelongsTo<Screen, $this>
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    /**
     * Get the user who claimed the code.
     *
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    /**
     * Find a registration by a display or typed code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()
            ->where('code_hash', RegistrationCode::hash($code))
            ->first();
    }

    /**
     * Determine if the registration can still be claimed.
     */
    public function isPending(): bool
    {
        return $this->consumed_at === null
            && $this->screen_id === null
            && $this->expires_at->isFuture();
    }

    /**
     * Determine if the registration has expired without being paired.
     */
    public function isExpired(): bool
    {
        return $this->consumed_at === null
            && $this->screen_id === null
            && $this->expires_at->isPast();
    }

    /**
     * Determine if the registration has been paired.
     */
    public function isPaired(): bool
    {
        return $this->screen_id !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
