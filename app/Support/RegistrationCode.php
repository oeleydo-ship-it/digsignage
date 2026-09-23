<?php

namespace App\Support;

final class RegistrationCode
{
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Generate a display code in the form ABCD-1234.
     */
    public static function generate(): string
    {
        $letters = '';

        for ($i = 0; $i < 4; $i++) {
            $letters .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
        }

        return $letters.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize a user or player supplied code.
     */
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '');
    }

    /**
     * Hash a code for storage and lookup.
     */
    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /**
     * Format a normalized code as ABCD-1234.
     */
    public static function format(string $code): string
    {
        $normalized = self::normalize($code);

        if (strlen($normalized) !== 8) {
            return $normalized;
        }

        return substr($normalized, 0, 4).'-'.substr($normalized, 4);
    }
}
