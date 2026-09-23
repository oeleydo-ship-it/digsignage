<?php

namespace App\Models;

use App\Enums\PlatformAuditAction;
use Database\Factories\PlatformAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property PlatformAuditAction $action
 * @property string|null $resource_type
 * @property int|null $resource_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 */
#[Fillable([
    'actor_id',
    'action',
    'resource_type',
    'resource_id',
    'before',
    'after',
    'ip_address',
    'user_agent',
    'created_at',
])]
class PlatformAudit extends Model
{
    /** @use HasFactory<PlatformAuditFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PlatformAuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
