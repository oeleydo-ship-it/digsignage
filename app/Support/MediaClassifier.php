<?php

namespace App\Support;

use App\Enums\MediaType;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

final class MediaClassifier
{
    /**
     * Infer a media type from an uploaded file.
     */
    public static function fromUpload(UploadedFile $file): ?MediaType
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = self::detectedMime($file);

        foreach (config('media.allowed_extensions') as $type => $extensions) {
            if (! in_array($extension, $extensions, true)) {
                continue;
            }

            if (self::mimeMatches((string) $type, $mime)) {
                return MediaType::from($type);
            }
        }

        return null;
    }

    /**
     * Infer a media type from a remote URL path.
     */
    public static function fromUrl(string $url): ?MediaType
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        foreach (config('media.allowed_extensions') as $type => $extensions) {
            if (in_array($extension, $extensions, true) && in_array($type, ['video', 'image', 'audio'], true)) {
                return MediaType::from($type);
            }
        }

        return null;
    }

    /**
     * Detect the MIME type from file contents, not the client header.
     */
    public static function detectedMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return (string) $file->getMimeType();
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);

        if (is_string($detected) && $detected !== '') {
            return $detected;
        }

        $guessed = MimeTypes::getDefault()->guessMimeType($path);

        return $guessed ?: (string) $file->getMimeType();
    }

    /**
     * Kilobyte upload limit for a media type.
     */
    public static function maxKilobytes(MediaType $type): int
    {
        return match ($type) {
            MediaType::Image => (int) config('media.max_image_kilobytes'),
            MediaType::HtmlPackage => (int) config('media.max_html_package_kilobytes'),
            default => (int) config('media.max_file_kilobytes'),
        };
    }

    protected static function mimeMatches(string $type, string $mime): bool
    {
        $allowedMimes = config('media.allowed_mimes.'.$type, []);

        if (in_array($mime, $allowedMimes, true)) {
            return true;
        }

        if ($type !== 'video') {
            return false;
        }

        return str_starts_with($mime, 'video/')
            || in_array($mime, ['inode/x-empty', 'application/x-empty'], true);
    }
}
