<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team-scoped storage for player fallback screen images.
 *
 * Images live on the public disk so paired players and the admin preview can
 * load them without authentication. Settings/metadata persist the public URL
 * because BuildPlayerManifest forwards the value verbatim as image_url.
 */
class FallbackImage
{
    public const DISK = 'public';

    public const URL_PREFIX = '/storage/';

    /**
     * Store an uploaded image for the team and return its public URL.
     */
    public static function store(int $teamId, UploadedFile $file, string $scope = 'team'): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = $scope.'-'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        $directory = $teamId.'/fallback';

        $path = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => __('The image could not be stored. Check available storage and retry.'),
            ]);
        }

        return self::URL_PREFIX.$path;
    }

    /**
     * Delete a previously stored fallback image, given its persisted URL.
     */
    public static function delete(mixed $url): void
    {
        $path = self::pathFromUrl($url);

        if ($path !== null && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Map a persisted public URL back to its disk path.
     */
    public static function pathFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, self::URL_PREFIX)) {
            return null;
        }

        $path = substr($url, strlen(self::URL_PREFIX));

        // Only ever touch team fallback directories.
        if (! preg_match('#^\d+/fallback/[^/]+$#', $path)) {
            return null;
        }

        return $path;
    }
}
