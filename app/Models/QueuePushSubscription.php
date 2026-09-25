<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer's browser that asked to be notified about their queue ticket.
 *
 * @property int $id
 * @property int $team_id
 * @property int $queue_ticket_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QueueTicket $ticket
 */
#[Fillable(['team_id', 'queue_ticket_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding'])]
class QueuePushSubscription extends Model
{
    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }
}
