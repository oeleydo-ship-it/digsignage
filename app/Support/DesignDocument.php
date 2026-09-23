<?php

namespace App\Support;

use App\Enums\DesignElementType;
use App\Widgets\WidgetRegistry;
use Illuminate\Support\Str;

final class DesignDocument
{
    /**
     * Default empty canvas document.
     *
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    public static function blank(int $width = 1920, int $height = 1080): array
    {
        return [
            'width' => $width,
            'height' => $height,
            'background' => '#111827',
            'elements' => [],
        ];
    }

    /**
     * Normalize and validate a stored design document.
     *
     * @param  array<string, mixed>  $document
     * @return array{width: int, height: int, background: string, elements: list<array<string, mixed>>}
     */
    public static function normalize(array $document, int $fallbackWidth = 1920, int $fallbackHeight = 1080): array
    {
        $width = max(320, min(7680, (int) ($document['width'] ?? $fallbackWidth)));
        $height = max(240, min(4320, (int) ($document['height'] ?? $fallbackHeight)));
        $background = is_string($document['background'] ?? null) ? $document['background'] : '#111827';
        $elements = [];

        foreach ($document['elements'] ?? [] as $element) {
            if (! is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');

            if ($type === 'chart') {
                $type = 'charts';
            }

            if (DesignElementType::tryFrom($type) === null && ! app(WidgetRegistry::class)->has($type)) {
                continue;
            }

            $elementWidth = max(8, min($width, (float) ($element['width'] ?? 200)));
            $elementHeight = max(8, min($height, (float) ($element['height'] ?? 80)));

            $elements[] = [
                'id' => is_string($element['id'] ?? null) && $element['id'] !== ''
                    ? $element['id']
                    : (string) Str::uuid(),
                'type' => $type,
                'name' => is_string($element['name'] ?? null) ? $element['name'] : ucfirst($type),
                'x' => max(0, min($width - $elementWidth, (float) ($element['x'] ?? 0))),
                'y' => max(0, min($height - $elementHeight, (float) ($element['y'] ?? 0))),
                'width' => $elementWidth,
                'height' => $elementHeight,
                'rotation' => (float) ($element['rotation'] ?? 0),
                'opacity' => max(0, min(1, (float) ($element['opacity'] ?? 1))),
                'zIndex' => (int) ($element['zIndex'] ?? 0),
                'locked' => (bool) ($element['locked'] ?? false),
                'hidden' => (bool) ($element['hidden'] ?? false),
                'props' => is_array($element['props'] ?? null) ? $element['props'] : [],
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'elements' => $elements,
        ];
    }
}
