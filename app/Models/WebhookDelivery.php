<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int $webhook_endpoint_id
 * @property WebhookEvent $event
 * @property array<string, mixed> $payload
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_code
 * @property string|null $last_error
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookEndpoint $endpoint
 */
#[Fillable([
    'uuid',
    'team_id',
    'webhook_endpoint_id',
    'event',
    'payload',
    'status',
    'attempts',
    'response_code',
    'last_error',
    'delivered_at',
])]
class WebhookDelivery extends Model
{
    use BelongsToTeam;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WebhookDelivery $delivery) {
            if (empty($delivery->uuid)) {
                $delivery->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'response_code' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }
}
