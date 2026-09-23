<?php

namespace Database\Seeders;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Services\Template\GenerateTemplateThumbnail;
use App\Support\CatalogTemplateLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CatalogTemplateSeeder extends Seeder
{
    /**
     * Seed published platform templates with ready-made layouts.
     */
    public function run(): void
    {
        $this->downloadCatalogPhotos();
        $thumbnails = app(GenerateTemplateThumbnail::class);

        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            $document = $definition['document'];

            $template = Template::query()
                ->withTrashed()
                ->whereNull('team_id')
                ->where(function ($query) use ($definition) {
                    $query->where('slug', $definition['key'])
                        ->orWhere('name', $definition['name']);
                })
                ->first();

            $attributes = [
                'slug' => $definition['key'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'category' => $definition['category'],
                'status' => TemplateStatus::Published,
                'width' => $document['width'],
                'height' => $document['height'],
                'document' => $document,
                'published_at' => $template?->published_at ?? now(),
                'archived_at' => null,
                'deleted_at' => null,
            ];

            if ($template === null) {
                $template = Template::query()->create([
                    'team_id' => null,
                    ...$attributes,
                ]);
            } else {
                if ($template->trashed()) {
                    $template->restore();
                }

                $template->fill($attributes);
                $template->save();
            }

            $thumbnails->handle($template);
        }
    }

    /**
     * Download royalty-free stock photos used by catalog image layers.
     */
    protected function downloadCatalogPhotos(): void
    {
        $directory = public_path('images/catalog');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return;
        }

        $photos = [
            'lobby-welcome' => ['width' => 1920, 'height' => 1080, 'seed' => 'digsignaged-lobby'],
            'retail-promo' => ['width' => 1920, 'height' => 1080, 'seed' => 'digsignaged-retail'],
            'social-community-wall' => ['width' => 1920, 'height' => 1080, 'seed' => 'digsignaged-community'],
            'lobby-welcome-portrait' => ['width' => 1080, 'height' => 1920, 'seed' => 'digsignaged-lobby-portrait'],
            'retail-promo-portrait' => ['width' => 1080, 'height' => 1920, 'seed' => 'digsignaged-retail-portrait'],
            'ceo-message' => ['width' => 960, 'height' => 1080, 'seed' => 'digsignaged-executive'],
            'daily-specials-board' => ['width' => 1920, 'height' => 520, 'seed' => 'digsignaged-food-hero'],
            'new-arrivals' => ['width' => 1080, 'height' => 1080, 'seed' => 'digsignaged-fashion'],
            'property-showcase' => ['width' => 1200, 'height' => 1080, 'seed' => 'digsignaged-property'],
            'sponsor-showcase' => ['width' => 1920, 'height' => 1080, 'seed' => 'digsignaged-event'],
            'square-brand-spotlight' => ['width' => 1080, 'height' => 720, 'seed' => 'digsignaged-brand'],
            'square-menu-special' => ['width' => 1080, 'height' => 540, 'seed' => 'digsignaged-dish'],
            'square-product-feature' => ['width' => 1080, 'height' => 620, 'seed' => 'digsignaged-product'],
            'portrait-ceo-message' => ['width' => 1080, 'height' => 864, 'seed' => 'digsignaged-ceo-portrait'],
            'portrait-property-listing' => ['width' => 1080, 'height' => 806, 'seed' => 'digsignaged-listing'],
            'now-hiring' => ['width' => 960, 'height' => 1080, 'seed' => 'digsignaged-hiring'],
            'onboarding-welcome' => ['width' => 1920, 'height' => 420, 'seed' => 'digsignaged-onboarding'],
            'hospitality-welcome' => ['width' => 1920, 'height' => 1080, 'seed' => 'digsignaged-hotel'],
            'corporate-lobby' => ['width' => 720, 'height' => 1080, 'seed' => 'digsignaged-corporate-lobby'],
            'transit-departures-table' => ['width' => 1920, 'height' => 320, 'seed' => 'digsignaged-transit'],
            'events-agenda' => ['width' => 1920, 'height' => 280, 'seed' => 'digsignaged-conference'],
            'internal-comms' => ['width' => 920, 'height' => 560, 'seed' => 'digsignaged-office'],
            'healthcare-waiting-room' => ['width' => 1920, 'height' => 180, 'seed' => 'digsignaged-clinic'],
        ];

        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            $document = $definition['document'];
            $photos[$definition['key']] ??= [
                'width' => max(480, (int) $document['width']),
                'height' => max(480, (int) $document['height']),
                'seed' => 'digsignaged-'.$definition['key'],
            ];
        }

        foreach ($photos as $key => $photo) {
            $path = $directory.DIRECTORY_SEPARATOR.$key.'.jpg';

            if (is_file($path) && filesize($path) > 15000) {
                continue;
            }

            $seed = $photo['seed'] ?? 'digsignaged-'.$key;
            $url = sprintf(
                'https://picsum.photos/seed/%s/%d/%d',
                rawurlencode($seed),
                (int) $photo['width'],
                (int) $photo['height'],
            );

            try {
                $response = Http::timeout(45)->get($url);

                if ($response->successful() && strlen($response->body()) > 1000) {
                    file_put_contents($path, $response->body);

                    continue;
                }
            } catch (\Throwable) {
                // Fall back to a local gradient when offline.
            }

            $this->writeGradientPlaceholder($path, (int) $photo['width'], (int) $photo['height']);
        }
    }

    /**
     * Write a gradient JPEG when stock photos cannot be downloaded.
     */
    protected function writeGradientPlaceholder(string $path, int $width, int $height): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            return;
        }

        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $canvas,
                $this->mix(0x1E, 0x33, $t),
                $this->mix(0x29, 0x41, $t),
                $this->mix(0x3B, 0x55, $t),
            );

            if ($color !== false) {
                imageline($canvas, 0, $y, $width - 1, $y, $color);
            }
        }

        imagejpeg($canvas, $path, 82);
        imagedestroy($canvas);
    }

    /**
     * @return int<0, 255>
     */
    protected function mix(int $from, int $to, float $t): int
    {
        $value = (int) round($from + (($to - $from) * $t));

        return max(0, min(255, $value));
    }
}
