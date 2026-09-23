<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ContentApprovalAction;
use Database\Factories\ContentApprovalEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property ContentApprovalAction $action
 * @property string $from_status
 * @property string $to_status
 * @property int $revision
 * @property int|null $user_id
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Model|null $approvable
 */
#[Fillable([
    'team_id',
    'approvable_type',
    'approvable_id',
    'action',
    'from_status',
    'to_status',
    'revision',
    'user_id',
    'comment',
])]
class ContentApprovalEvent extends Model
{
    /** @use HasFactory<ContentApprovalEventFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

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
            'action' => ContentApprovalAction::class,
            'revision' => 'integer',
        ];
    }
}
