<?php

namespace App\Support;

final class DeviceToken
{
    /**
     * Lookup hash for a player device credential.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
