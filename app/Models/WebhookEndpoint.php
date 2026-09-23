<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WebhookEvent;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property Carbon|null $last_delivery_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, WebhookDelivery> $deliveries
 */
#[Fillable(['team_id', 'user_id', 'url', 'secret', 'events', 'is_active', 'last_delivery_at'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(WebhookEvent|string $event): bool
    {
        $value = $event instanceof WebhookEvent ? $event->value : $event;

        return in_array('*', $this->events, true) || in_array($value, $this->events, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }
}
