<?php

namespace App\Enums;

enum EmergencySeverity: string
{
    case Emergency = 'emergency';
    case Critical = 'critical';

    /**
     * Get the display label for the severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Emergency => 'Emergency',
            self::Critical => 'Critical',
        };
    }

    /**
     * Playback rank. Higher values override lower ones.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Emergency => 20,
            self::Critical => 10,
        };
    }

    /**
     * Default overlay background.
     */
    public function defaultBackground(): string
    {
        return match ($this) {
            self::Emergency => '#b91c1c',
            self::Critical => '#b45309',
        };
    }
}
