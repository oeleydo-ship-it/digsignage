<?php

return [
    'registration' => [
        'ttl_minutes' => (int) env('SIGNAGE_REGISTRATION_TTL', 15),
        'token_ttl_minutes' => (int) env('SIGNAGE_DEVICE_TOKEN_TTL', 10),
        'start_per_minute' => (int) env('SIGNAGE_REGISTRATION_START_PER_MINUTE', 10),
        'status_per_minute' => (int) env('SIGNAGE_REGISTRATION_STATUS_PER_MINUTE', 60),
        'pair_per_minute' => (int) env('SIGNAGE_PAIR_PER_MINUTE', 10),
    ],

    'player' => [
        'heartbeat_seconds' => (int) env('SIGNAGE_PLAYER_HEARTBEAT', 30),
        'manifest_poll_seconds' => (int) env('SIGNAGE_PLAYER_MANIFEST_POLL', 20),
        'heartbeat_per_minute' => (int) env('SIGNAGE_PLAYER_HEARTBEAT_PER_MINUTE', 60),
        'telemetry_batch_max' => (int) env('SIGNAGE_PLAYER_TELEMETRY_BATCH', 200),
        'telemetry_queue_max' => (int) env('SIGNAGE_PLAYER_TELEMETRY_QUEUE', 500),
        'telemetry_per_minute' => (int) env('SIGNAGE_PLAYER_TELEMETRY_PER_MINUTE', 30),
        'playback_per_minute' => (int) env('SIGNAGE_PLAYER_PLAYBACK_PER_MINUTE', 120),
        'command_ttl_minutes' => (int) env('SIGNAGE_PLAYER_COMMAND_TTL', 10),
        'command_poll_seconds' => (int) env('SIGNAGE_PLAYER_COMMAND_POLL', 8),
        // Server-side manifest cache; content writes invalidate immediately.
        'manifest_cache_seconds' => (int) env('SIGNAGE_PLAYER_MANIFEST_CACHE', 20),
    ],

    'analytics' => [
        'heartbeat_sample_seconds' => (int) env('SIGNAGE_ANALYTICS_HEARTBEAT_SAMPLE', 300),
        'storage_warning_ratio' => (float) env('SIGNAGE_ANALYTICS_STORAGE_WARNING', 0.1),
        // Analytics report cache; new telemetry invalidates immediately.
        'report_cache_seconds' => (int) env('SIGNAGE_ANALYTICS_REPORT_CACHE', 60),
    ],

    'notifications' => [
        'dedupe_seconds' => (int) env('SIGNAGE_NOTIFICATION_DEDUPE', 300),
    ],

    'monitoring' => [
        'healthy_seconds' => (int) env('SIGNAGE_MONITOR_HEALTHY_SECONDS', 120),
        'warning_seconds' => (int) env('SIGNAGE_MONITOR_WARNING_SECONDS', 300),
    ],
];
