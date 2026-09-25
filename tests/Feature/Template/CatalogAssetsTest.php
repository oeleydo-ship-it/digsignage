<?php

namespace Tests\Feature\Template;

use App\Enums\DesignElementType;
use App\Support\CatalogArtwork;
use App\Support\CatalogShowcaseTemplates;
use App\Support\CatalogTemplateLibrary;
use App\Support\DesignStickers;
use App\Widgets\WidgetRegistry;
use Tests\TestCase;

class CatalogAssetsTest extends TestCase
{
    public function test_template_keys_are_unique(): void
    {
        $keys = array_column(CatalogTemplateLibrary::definitions(), 'key');

        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_showcase_templates_are_part_of_the_catalog(): void
    {
        $catalog = array_column(CatalogTemplateLibrary::definitions(), 'key');

        foreach (CatalogShowcaseTemplates::definitions() as $definition) {
            $this->assertContains($definition['key'], $catalog);
        }
    }

    public function test_room_booking_templates_cover_four_screen_shapes(): void
    {
        $catalog = collect(CatalogTemplateLibrary::definitions())->keyBy('key');
        $expected = [
            'room-door-sign' => [1920, 1080, 'room_status'],
            'portrait-room-availability' => [1080, 1920, 'room_board'],
            'room-door-panel' => [1280, 800, 'room_status'],
            'square-room-sign' => [1080, 1080, 'room_status'],
        ];

        foreach ($expected as $key => [$width, $height, $widget]) {
            $this->assertTrue($catalog->has($key), "Missing template {$key}");
            $document = $catalog[$key]['document'];

            $this->assertSame([$width, $height], [$document['width'], $document['height']], $key);
            $this->assertContains($widget, array_column($document['elements'], 'type'), $key);

            foreach ($document['elements'] as $element) {
                $this->assertLessThanOrEqual($width, $element['x'] + $element['width'], "{$key}: {$element['name']} overflows");
                $this->assertLessThanOrEqual($height, $element['y'] + $element['height'], "{$key}: {$element['name']} overflows");

                if ($element['type'] === 'room_status') {
                    // Templates ship without a room; each copy picks its own.
                    $this->assertSame('0', $element['props']['room_id']);
                }
            }
        }
    }

    public function test_every_catalog_image_ships_with_the_app(): void
    {
        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            foreach ($definition['document']['elements'] as $element) {
                $src = $element['props']['src'] ?? null;

                if (! is_string($src) || ! str_starts_with($src, '/images/')) {
                    continue;
                }

                $this->assertFileExists(
                    public_path(ltrim($src, '/')),
                    "{$definition['key']} references a missing image: {$src}",
                );
            }
        }
    }

    public function test_every_catalog_element_type_is_supported(): void
    {
        $registry = app(WidgetRegistry::class);

        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            $source = count($definition['document']['elements']);

            foreach ($definition['document']['elements'] as $element) {
                $this->assertTrue(
                    DesignElementType::tryFrom($element['type']) !== null || $registry->has($element['type']),
                    "{$definition['key']} uses unknown element type {$element['type']}",
                );
            }

            $this->assertGreaterThan(0, $source, "{$definition['key']} has no elements");
        }
    }

    public function test_every_catalog_icon_exists_in_the_designer_icon_set(): void
    {
        $registry = (string) file_get_contents(resource_path('js/lib/design-icons.ts'));
        // Entries may be formatted on one line or spread across several.
        preg_match_all("/^\s+'?([a-z0-9-]+)'?: \{\s*label:/m", $registry, $matches);
        $icons = $matches[1];

        $this->assertNotEmpty($icons);

        foreach (CatalogTemplateLibrary::definitions() as $definition) {
            foreach ($definition['document']['elements'] as $element) {
                if ($element['type'] !== 'icon') {
                    continue;
                }

                $this->assertContains(
                    $element['props']['icon'] ?? null,
                    $icons,
                    "{$definition['key']} uses an icon the designer cannot render",
                );
            }
        }
    }

    public function test_generated_artwork_is_distinct_and_deterministic(): void
    {
        $keys = array_keys(CatalogArtwork::themes());
        $svgs = array_map(fn (string $key): string => CatalogArtwork::svg($key), $keys);

        $this->assertSame(count($svgs), count(array_unique($svgs)));
        $this->assertSame(CatalogArtwork::svg($keys[0]), CatalogArtwork::svg($keys[0]));

        foreach (array_slice($svgs, 0, 5) as $svg) {
            $this->assertNotFalse(simplexml_load_string($svg));
        }
    }

    public function test_stickers_are_valid_svg(): void
    {
        foreach (array_keys(DesignStickers::catalog()) as $key) {
            $this->assertNotFalse(
                simplexml_load_string(DesignStickers::svg($key)),
                "Sticker {$key} is not valid SVG",
            );
        }
    }
}
