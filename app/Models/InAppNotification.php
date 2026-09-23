<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SignageAlert;
use Database\Factories\InAppNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property SignageAlert $event
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'event', 'title', 'body', 'data', 'read_at'])]
class InAppNotification extends Model
{
    /** @use HasFactory<InAppNotificationFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shape shared with the header inbox and the notifications page.
     *
     * @return array{
     *     id: int,
     *     event: string,
     *     event_label: string,
     *     title: string,
     *     body: string,
     *     url: string|null,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    public function toInboxItem(string $teamSlug): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->destinationUrl($teamSlug),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Deep-link to the screen, content, or settings page this alert is about.
     */
    public function destinationUrl(string $teamSlug): ?string
    {
        if ($teamSlug === '') {
            return null;
        }

        $data = $this->data ?? [];

        return match ($this->event) {
            SignageAlert::ScreenOffline,
            SignageAlert::ScreenRestored,
            SignageAlert::StorageLow,
            SignageAlert::SyncFailed,
            SignageAlert::PlayerOutdated,
            SignageAlert::DeviceCommandFailed => $this->screenUrl($teamSlug, $data),
            SignageAlert::MediaProcessingFailed => route('media.index', $teamSlug),
            SignageAlert::EmergencyActivated,
            SignageAlert::EmergencyStopped => isset($data['emergency_id']) && is_numeric($data['emergency_id'])
                ? route('emergencies.show', [$teamSlug, $data['emergency_id']])
                : route('emergencies.index', $teamSlug),
            SignageAlert::SubscriptionIssue => route('billing.index', $teamSlug),
            SignageAlert::ContentPendingApproval => $this->contentUrl($teamSlug, $data)
                ?? route('approvals.index', $teamSlug),
            SignageAlert::ContentApproved,
            SignageAlert::ContentRejected,
            SignageAlert::ContentPublished => $this->contentUrl($teamSlug, $data),
            SignageAlert::QueueAverageWaitHigh,
            SignageAlert::QueueWaitingCountHigh,
            SignageAlert::QueueCustomerWaitHigh,
            SignageAlert::QueueNoCounterAvailable,
            SignageAlert::QueueCapacityReached,
            SignageAlert::QueueCounterOffline => route('queue.overview', $teamSlug),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function screenUrl(string $teamSlug, array $data): string
    {
        if (isset($data['screen_id']) && is_numeric($data['screen_id'])) {
            return route('screens.commands.index', [$teamSlug, $data['screen_id']]);
        }

        return route('screens.index', $teamSlug);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function contentUrl(string $teamSlug, array $data): ?string
    {
        $id = $data['id'] ?? null;
        $type = $data['type'] ?? null;

        if (! is_numeric($id) || ! is_string($type)) {
            return null;
        }

        return match ($type) {
            'playlist' => route('playlists.show', [$teamSlug, $id]),
            'design' => route('designs.show', [$teamSlug, $id]),
            'template' => route('templates.show', [$teamSlug, $id]),
            'channel' => route('channels.show', [$teamSlug, $id]),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SignageAlert::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
