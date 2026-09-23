<?php

namespace App\Support;

use App\Enums\PlaybackStatus;
use Illuminate\Validation\Rule;

class PlayerTelemetry
{
    /**
     * Validation rules for a single heartbeat payload.
     *
     * @return array<string, mixed>
     */
    public static function heartbeatRules(): array
    {
        return [
            'player_version' => ['nullable', 'string', 'max:64'],
            'current_content' => ['nullable', 'string', 'max:255'],
            'storage_free' => ['nullable', 'integer', 'min:0'],
            'storage_total' => ['nullable', 'integer', 'min:0'],
            'memory_usage' => ['nullable', 'integer', 'min:0'],
            'cpu_usage' => ['nullable', 'numeric', 'min:0'],
            'network_status' => ['nullable', 'string', 'max:64'],
            'last_error' => ['nullable', 'string', 'max:2000'],
            'manifest_version' => ['nullable', 'integer'],
            'playing_offline' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validation rules for a single playback event payload.
     *
     * @return array<string, mixed>
     */
    public static function playbackRules(): array
    {
        return [
            'item_key' => ['nullable', 'string', 'max:64'],
            'asset_key' => ['nullable', 'string', 'max:64'],
            'content_id' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'channel_id' => ['nullable', 'integer'],
            'playlist_id' => ['nullable', 'integer'],
            'schedule_id' => ['nullable', 'integer'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'played_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::enum(PlaybackStatus::class)],
        ];
    }
}
