<?php

namespace App\Enums;

enum DeviceCommandType: string
{
    case Refresh = 'refresh';
    case Reload = 'reload';
    case Sync = 'sync';
    case RestartPlayer = 'restart-player';
    case ClearCache = 'clear-cache';
    case TakeScreenshot = 'take-screenshot';
    case ChangeChannel = 'change-channel';
    case EmergencyStart = 'emergency-start';
    case EmergencyStop = 'emergency-stop';
    case UpdateSettings = 'update-settings';

    /**
     * Get the display label for the command.
     */
    public function label(): string
    {
        return match ($this) {
            self::Refresh => 'Refresh',
            self::Reload => 'Reload',
            self::Sync => 'Sync',
            self::RestartPlayer => 'Restart player',
            self::ClearCache => 'Clear cache',
            self::TakeScreenshot => 'Take screenshot',
            self::ChangeChannel => 'Change channel',
            self::EmergencyStart => 'Start emergency',
            self::EmergencyStop => 'Stop emergency',
            self::UpdateSettings => 'Update settings',
        };
    }
}
