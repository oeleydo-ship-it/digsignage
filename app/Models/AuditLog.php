<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AuditAction;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $resource_type
 * @property int|null $resource_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable([
    'team_id',
    'user_id',
    'action',
    'resource_type',
    'resource_id',
    'before',
    'after',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToTeam, HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
            'resource_id' => 'integer',
        ];
    }
}
