<?php

namespace App\Console\Commands;

use App\Support\CatalogArtwork;
use App\Support\DesignStickers;
use Illuminate\Console\Command;

class GenerateCatalogArtworkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catalog:artwork
                            {--prune : Delete artwork files that no longer have a theme}';

    /**
     * @var string
     */
    protected $description = 'Write catalog backgrounds, designer stickers, and the graphics library manifest';

    public function handle(): int
    {
        $directory = public_path(CatalogArtwork::DIRECTORY);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}.");

            return self::FAILURE;
        }

        $themes = CatalogArtwork::themes();
        $written = 0;

        foreach (array_keys($themes) as $key) {
            [$width, $height] = $this->dimensions($key);
            $path = $directory.DIRECTORY_SEPARATOR.$key.'.svg';
            $svg = CatalogArtwork::svg($key, $width, $height);

            if (is_file($path) && file_get_contents($path) === $svg) {
                continue;
            }

            file_put_contents($path, $svg);
            $written++;
        }

        $this->info($written === 0
            ? 'Catalog artwork is already up to date ('.count($themes).' files).'
            : "Wrote {$written} of ".count($themes).' catalog artwork file(s).');

        if ($this->option('prune')) {
            $this->prune($directory, array_keys($themes));
        }

        $stickers = $this->writeStickers();
        $this->writeManifest(array_keys($themes));

        $this->info("Wrote {$stickers} sticker file(s) and the graphics manifest.");

        return self::SUCCESS;
    }

    protected function writeStickers(): int
    {
        $directory = public_path(DesignStickers::DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $written = 0;

        foreach (array_keys(DesignStickers::catalog()) as $key) {
            $path = $directory.DIRECTORY_SEPARATOR.$key.'.svg';
            $svg = DesignStickers::svg($key);

            if (is_file($path) && file_get_contents($path) === $svg) {
                continue;
            }

            file_put_contents($path, $svg);
            $written++;
        }

        return $written;
    }

    /**
     * The designer's graphics panel reads this file, so the library grows
     * whenever artwork or stickers are added here without a frontend change.
     *
     * @param  list<string>  $backgrounds
     */
    protected function writeManifest(array $backgrounds): void
    {
        $manifest = [
            'backgrounds' => array_map(function (string $key): array {
                [$width, $height] = $this->dimensions($key);

                return [
                    'key' => $key,
                    'label' => ucwords(str_replace('-', ' ', $key)),
                    'src' => CatalogArtwork::source($key),
                    'width' => $width,
                    'height' => $height,
                ];
            }, $backgrounds),
            'stickers' => array_map(
                fn (string $key, array $meta): array => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'src' => DesignStickers::source($key),
                    'width' => $meta['width'],
                    'height' => $meta['height'],
                ],
                array_keys(DesignStickers::catalog()),
                DesignStickers::catalog(),
            ),
        ];

        file_put_contents(
            public_path('images/library/manifest.json'),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * Match the artwork aspect ratio to the layouts that use it so the
     * browser never has to crop a landscape image into a 9:16 frame.
     *
     * @return array{int, int}
     */
    protected function dimensions(string $key): array
    {
        if (str_starts_with($key, 'square-')) {
            return [1200, 1200];
        }

        if (str_contains($key, 'portrait')) {
            return [900, 1600];
        }

        return [1600, 900];
    }

    /**
     * @param  list<string>  $keys
     */
    protected function prune(string $directory, array $keys): void
    {
        $removed = 0;

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.svg') ?: [] as $file) {
            if (! in_array(pathinfo($file, PATHINFO_FILENAME), $keys, true)) {
                unlink($file);
                $removed++;
            }
        }

        $this->info("Pruned {$removed} orphaned artwork file(s).");
    }
}
