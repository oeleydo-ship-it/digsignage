<?php

namespace App\Services\Deploy;

use App\Support\AppVersion;
use App\Support\ReleaseNotes;
use ZipArchive;

/**
 * Validates and unpacks release zips. Accepts both the packages built by the
 * deploy workflow (files at the zip root) and GitHub source zips (files under
 * a single "owner-repo-sha/" folder).
 */
class ReleaseArchive
{
    private const MAX_ENTRIES = 200_000;

    /** Paths never copied into a release: the server keeps its own. */
    private const SKIPPED = ['.env', 'storage/', 'node_modules/', '.git/', 'bootstrap/cache/'];

    public function inspect(string $path): ReleasePackage
    {
        $zip = $this->open($path);

        try {
            $count = $zip->numFiles;

            if ($count === 0 || $count > self::MAX_ENTRIES) {
                throw new ReleaseException(__('The package is empty or has too many files.'));
            }

            $limit = max(1, (int) config('deploy.max_extracted_mb')) * 1024 * 1024;
            $total = 0;

            for ($index = 0; $index < $count; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new ReleaseException(__('The package could not be read.'));
                }

                $this->assertSafeEntry($zip, $index, (string) $stat['name']);
                $total += (int) $stat['size'];

                if ($total > $limit) {
                    throw new ReleaseException(__('The package unpacks to more than :size MB.', ['size' => (int) config('deploy.max_extracted_mb')]));
                }
            }

            $root = $this->root($zip);

            foreach (['artisan', 'composer.json', 'VERSION'] as $required) {
                if ($zip->locateName($root.$required) === false) {
                    throw new ReleaseException(__('This is not a DigSignage release: :file is missing.', ['file' => $required]));
                }
            }

            $version = AppVersion::normalize((string) $zip->getFromName($root.'VERSION'));

            if ($version === null) {
                throw new ReleaseException(__('The VERSION file must contain a version such as 1.4.0.'));
            }

            $changelog = $zip->getFromName($root.'CHANGELOG.md');
            $notes = is_string($changelog)
                ? ReleaseNotes::fromChangelog($changelog, $version)
                : ['title' => null, 'notes' => null, 'features' => []];

            return new ReleasePackage(
                version: $version,
                root: $root,
                title: $notes['title'],
                notes: $notes['notes'],
                features: $notes['features'],
                checksum: (string) hash_file('sha256', $path),
                size: (int) filesize($path),
                hasVendor: $zip->locateName($root.'vendor/autoload.php') !== false,
                hasBuild: $zip->locateName($root.'public/build/manifest.json') !== false,
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * Unpack the package's files into an empty release directory.
     */
    public function extract(string $path, ReleasePackage $package, string $destination): void
    {
        if (is_dir($destination) && (scandir($destination) ?: []) !== ['.', '..']) {
            throw new ReleaseException(__('The release folder :path already exists.', ['path' => $destination]));
        }

        $zip = $this->open($path);

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                $this->assertSafeEntry($zip, $index, $name);

                if (! str_starts_with($name, $package->root)) {
                    continue;
                }

                $relative = substr($name, strlen($package->root));

                if ($relative === '' || $this->skipped($relative)) {
                    continue;
                }

                $target = $destination.'/'.$relative;

                if (str_ends_with($name, '/')) {
                    $this->directory($target);

                    continue;
                }

                $this->directory(dirname($target));
                $this->copy($zip, $index, $target);
            }
        } finally {
            $zip->close();
        }
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ReleaseException(__('The file is not a valid zip archive.'));
        }

        return $zip;
    }

    /**
     * Refuse anything that could write outside the release folder.
     */
    private function assertSafeEntry(ZipArchive $zip, int $index, string $name): void
    {
        $unsafe = $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name) === 1
            || in_array('..', explode('/', $name), true);

        if ($unsafe) {
            throw new ReleaseException(__('The package contains an unsafe path: :name', ['name' => mb_substr($name, 0, 120)]));
        }

        $system = 0;
        $attributes = 0;

        if ($zip->getExternalAttributesIndex($index, $system, $attributes)
            && $system === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & 0170000) === 0120000) {
            throw new ReleaseException(__('The package contains a symbolic link: :name', ['name' => mb_substr($name, 0, 120)]));
        }
    }

    /**
     * "" when the app sits at the zip root, or "folder/" for GitHub source
     * zips that wrap everything in one directory.
     */
    private function root(ZipArchive $zip): string
    {
        if ($zip->locateName('artisan') !== false) {
            return '';
        }

        $roots = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (preg_match('#^([^/]+)/artisan$#', $name, $match) === 1) {
                $roots[] = $match[1].'/';
            }
        }

        if (count($roots) !== 1) {
            throw new ReleaseException(__('This is not a DigSignage release: artisan is missing.'));
        }

        return $roots[0];
    }

    private function skipped(string $relative): bool
    {
        foreach (self::SKIPPED as $skip) {
            if ($relative === rtrim($skip, '/') || (str_ends_with($skip, '/') && str_starts_with($relative, $skip))) {
                return true;
            }
        }

        return false;
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new ReleaseException(__('Could not create :path.', ['path' => $path]));
        }
    }

    private function copy(ZipArchive $zip, int $index, string $target): void
    {
        $input = $zip->getStreamIndex($index);
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new ReleaseException(__('Could not write :path.', ['path' => $target]));
        }

        try {
            stream_copy_to_stream($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }
    }
}
