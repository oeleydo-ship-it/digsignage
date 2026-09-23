<?php

namespace App\Enums;

enum AnalyticsEventType: string
{
    case Heartbeat = 'heartbeat';
    case DeviceError = 'device_error';
    case DownloadFailure = 'download_failure';
    case Crash = 'crash';
    case SyncFailure = 'sync_failure';
    case CommandFailure = 'command_failure';

    public function label(): string
    {
        return match ($this) {
            self::Heartbeat => 'Heartbeat',
            self::DeviceError => 'Device error',
            self::DownloadFailure => 'Failed download',
            self::Crash => 'Player crash',
            self::SyncFailure => 'Synchronization failure',
            self::CommandFailure => 'Command failure',
        };
    }
}
