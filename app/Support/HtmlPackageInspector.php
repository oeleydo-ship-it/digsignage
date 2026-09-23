<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use ZipArchive;

final class HtmlPackageInspector
{
    /**
     * Reject zip packages that contain traversal paths or executable files.
     */
    public static function assertSafe(string $absolutePath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw ValidationException::withMessages([
                'files' => __('The HTML package could not be opened.'),
            ]);
        }

        try {
            $blocked = config('media.blocked_archive_extensions');

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);

                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw ValidationException::withMessages([
                        'files' => __('The HTML package contains an unsafe path.'),
                    ]);
                }

                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($extension !== '' && in_array($extension, $blocked, true)) {
                    throw ValidationException::withMessages([
                        'files' => __('The HTML package contains a disallowed file type.'),
                    ]);
                }
            }
        } finally {
            $zip->close();
        }
    }
}
