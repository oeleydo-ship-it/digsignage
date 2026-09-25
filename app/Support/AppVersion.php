<?php

namespace App\Support;

/**
 * The running application's version, read from the VERSION file that ships
 * with every release, plus helpers for comparing version strings.
 */
final class AppVersion
{
    private const PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    public static function current(): string
    {
        $path = base_path('VERSION');
        $version = is_file($path) ? self::normalize((string) file_get_contents($path)) : null;

        return $version ?? '0.0.0';
    }

    /**
     * Accepts "1.2.3", "v1.2.3" or "V1.2.3-beta.1"; returns null for
     * anything that is not a semantic version.
     */
    public static function normalize(?string $version): ?string
    {
        $version = ltrim(trim((string) $version), 'vV');

        return preg_match(self::PATTERN, $version) === 1 ? $version : null;
    }

    public static function isNewer(string $candidate, ?string $than = null): bool
    {
        return version_compare($candidate, $than ?? self::current(), '>');
    }
}
