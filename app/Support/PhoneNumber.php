<?php

namespace App\Support;

/**
 * Normalizes phone numbers to E.164 (+15551234567), the format SMS and
 * WhatsApp providers require.
 */
final class PhoneNumber
{
    public static function e164(?string $number): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $digits = preg_replace('/[\s().\-\/]/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return preg_match('/^\+[1-9]\d{6,14}$/', $digits) === 1 ? $digits : null;
    }
}
