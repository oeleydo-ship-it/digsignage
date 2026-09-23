<?php

namespace App\Actions\Player;

use App\Enums\PlaybackStatus;
use App\Models\Channel;
use App\Models\PlayerPlaybackEvent;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use Carbon\CarbonImmutable;

class RecordPlayerPlayback
{
    /**
     * Persist a proof-of-play event, including events queued while offline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Screen $screen, array $payload): PlayerPlaybackEvent
    {
        $startedAt = $this->timestamp($payload['started_at'] ?? $payload['played_at'] ?? null) ?? now();
        $endedAt = $this->timestamp($payload['ended_at'] ?? null);
        $durationMs = isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null;

        if ($durationMs === null && $endedAt !== null) {
            $durationMs = max(0, (int) $startedAt->diffInMilliseconds($endedAt));
        }

        $status = PlaybackStatus::tryFrom((string) ($payload['status'] ?? PlaybackStatus::Completed->value))
            ?? PlaybackStatus::Completed;
        $contentId = $this->nullableString($payload['content_id'] ?? null)
            ?? $this->nullableString($payload['item_key'] ?? null)
            ?? $this->nullableString($payload['asset_key'] ?? null);

        return PlayerPlaybackEvent::query()->create([
            'team_id' => $screen->team_id,
            'screen_id' => $screen->id,
            'location_id' => $screen->location_id,
            'channel_id' => $this->ownedId(Channel::class, $screen->team_id, $payload['channel_id'] ?? null),
            'playlist_id' => $this->ownedId(Playlist::class, $screen->team_id, $payload['playlist_id'] ?? null),
            'schedule_id' => $this->ownedId(Schedule::class, $screen->team_id, $payload['schedule_id'] ?? null),
            'item_key' => $this->nullableString($payload['item_key'] ?? null),
            'asset_key' => $this->nullableString($payload['asset_key'] ?? null),
            'content_id' => $contentId,
            'title' => $this->nullableString($payload['title'] ?? null),
            'duration_ms' => $durationMs,
            'played_at' => $startedAt,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'status' => $status,
        ]);
    }

    protected function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function ownedId(string $model, int $teamId, mixed $id): ?int
    {
        $key = (int) $id;

        if ($key < 1) {
            return null;
        }

        $exists = false;

        if ($model === Channel::class) {
            $exists = Channel::query()->whereKey($key)->where('team_id', $teamId)->exists();
        } elseif ($model === Playlist::class) {
            $exists = Playlist::query()->whereKey($key)->where('team_id', $teamId)->exists();
        } elseif ($model === Schedule::class) {
            $exists = Schedule::query()->whereKey($key)->where('team_id', $teamId)->exists();
        }

        return $exists ? $key : null;
    }
}
