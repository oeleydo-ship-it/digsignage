<?php

namespace App\Support;

final class PlayerVersion
{
    /**
     * Compare player versions after stripping non-numeric prefixes.
     */
    public static function isOutdated(?string $current, ?string $minimum): bool
    {
        $min = self::normalize($minimum);
        $have = self::normalize($current);

        if ($min === null || $have === null) {
            return false;
        }

        return version_compare($have, $min, '<');
    }

    public static function normalize(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $trimmed = trim($version);

        if ($trimmed === '') {
            return null;
        }

        $numeric = preg_replace('/[^0-9.]+/', '', $trimmed);

        if (! is_string($numeric) || $numeric === '' || $numeric === '.') {
            return null;
        }

        return $numeric;
    }
}
