<?php

namespace App\Support;

use App\Enums\AnalyticsEventType;

final class ClassifyPlayerAnalytics
{
    /**
     * Map heartbeat telemetry onto an analytics event type.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromHeartbeat(array $payload): AnalyticsEventType
    {
        $error = strtolower(trim((string) ($payload['last_error'] ?? '')));

        if ($error !== '' && preg_match('/crash|exception|segfault|out of memory|\boom\b/', $error) === 1) {
            return AnalyticsEventType::Crash;
        }

        if ($error !== '' && preg_match('/download|asset|checksum|cache/', $error) === 1) {
            return AnalyticsEventType::DownloadFailure;
        }

        if ((bool) ($payload['playing_offline'] ?? false) || ($error !== '' && preg_match('/sync|offline/', $error) === 1)) {
            return AnalyticsEventType::SyncFailure;
        }

        if ($error !== '') {
            return AnalyticsEventType::DeviceError;
        }

        return AnalyticsEventType::Heartbeat;
    }

    public static function storageWarning(mixed $free, mixed $total): bool
    {
        $freeBytes = is_numeric($free) ? (int) $free : null;
        $totalBytes = is_numeric($total) ? (int) $total : null;

        if ($freeBytes === null || $totalBytes === null || $totalBytes <= 0) {
            return false;
        }

        $ratio = (float) config('signage.analytics.storage_warning_ratio', 0.1);

        return ($freeBytes / $totalBytes) <= $ratio;
    }
}
