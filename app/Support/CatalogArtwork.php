<?php

namespace App\Support;

/**
 * Generates the background artwork shipped with the ready-made catalog.
 *
 * Every layout used to share one stock photo, so the gallery rendered 30+
 * identical-looking cards. Each template now gets its own deterministic SVG
 * built from a themed palette and motif: license-free, a couple of kilobytes,
 * and resolution independent on 4K screens.
 */
final class CatalogArtwork
{
    public const DIRECTORY = 'images/catalog';

    /**
     * Motifs, chosen per template so neighbouring cards never repeat.
     */
    private const MOTIFS = ['mesh', 'rays', 'waves', 'grid', 'arcs', 'bokeh', 'prism', 'contour'];

    /**
     * Palette and motif for every artwork file the catalog references.
     *
     * Keys match the file name without its extension.
     *
     * @return array<string, array{colors: list<string>, motif: string, accent: string}>
     */
    public static function themes(): array
    {
        return [
            // Corporate and internal communications.
            'lobby-welcome' => self::theme(['#0f2d5e', '#1d4ed8', '#38bdf8'], 'mesh', '#f8fafc'),
            'lobby-welcome-portrait' => self::theme(['#0b1f45', '#1e40af', '#60a5fa'], 'rays', '#e0f2fe'),
            'corporate-lobby' => self::theme(['#111827', '#1f3a8a', '#0ea5e9'], 'prism', '#e2e8f0'),
            'internal-comms' => self::theme(['#0b3b36', '#0f766e', '#34d399'], 'waves', '#ecfdf5'),
            'portrait-internal-comms' => self::theme(['#052e2b', '#0d9488', '#5eead4'], 'arcs', '#f0fdfa'),
            'ceo-message' => self::theme(['#1a1633', '#4c1d95', '#a78bfa'], 'rays', '#f5f3ff'),
            'portrait-ceo-message' => self::theme(['#170f2e', '#5b21b6', '#c4b5fd'], 'mesh', '#faf5ff'),
            'onboarding-welcome' => self::theme(['#0d2f4f', '#0369a1', '#7dd3fc'], 'bokeh', '#f0f9ff'),
            'kpi-dashboard' => self::theme(['#0a1120', '#155e75', '#22d3ee'], 'grid', '#ecfeff'),
            'portrait-kpi-stack' => self::theme(['#081019', '#0e7490', '#67e8f9'], 'grid', '#cffafe'),
            'team-metrics-api' => self::theme(['#101828', '#1e3a8a', '#818cf8'], 'contour', '#eef2ff'),
            'meeting-room' => self::theme(['#132a13', '#166534', '#4ade80'], 'grid', '#f0fdf4'),
            'meeting-agenda-board' => self::theme(['#1c2431', '#334155', '#94a3b8'], 'grid', '#f8fafc'),
            'now-hiring' => self::theme(['#2b1206', '#c2410c', '#fbbf24'], 'rays', '#fff7ed'),
            'portrait-hiring-board' => self::theme(['#3b1206', '#ea580c', '#fcd34d'], 'prism', '#fffbeb'),
            'square-hiring' => self::theme(['#23150a', '#b45309', '#fde68a'], 'arcs', '#fffbeb'),

            // Healthcare.
            'healthcare-waiting-room' => self::theme(['#082f49', '#0284c7', '#7dd3fc'], 'waves', '#f0f9ff'),
            'healthcare-wayfinding' => self::theme(['#0c2340', '#1d4ed8', '#93c5fd'], 'arcs', '#eff6ff'),
            'patient-safety-reminder' => self::theme(['#102a43', '#0e7490', '#5eead4'], 'contour', '#ecfeff'),
            'clinic-hours-board' => self::theme(['#0b2545', '#1e5f9e', '#a5d8ff'], 'grid', '#f1f5f9'),
            'portrait-patient-info' => self::theme(['#06283d', '#0891b2', '#a5f3fc'], 'waves', '#ecfeff'),
            'square-wellness-tip' => self::theme(['#0f2e2a', '#15803d', '#86efac'], 'bokeh', '#f0fdf4'),

            // Food service.
            'restaurant-menu' => self::theme(['#1c1206', '#7c2d12', '#f59e0b'], 'bokeh', '#fffbeb'),
            'menu-breakfast-lunch' => self::theme(['#241505', '#92400e', '#fbbf24'], 'arcs', '#fef3c7'),
            'daily-specials-board' => self::theme(['#22160a', '#b45309', '#fcd34d'], 'rays', '#fffbeb'),
            'allergen-notice' => self::theme(['#2a1508', '#a16207', '#fde047'], 'grid', '#fefce8'),
            'portrait-menu-board' => self::theme(['#1a1005', '#854d0e', '#facc15'], 'waves', '#fefce8'),
            'square-menu-special' => self::theme(['#2c1408', '#c2410c', '#fdba74'], 'prism', '#fff7ed'),

            // Retail.
            'retail-promo' => self::theme(['#2d0a3d', '#9333ea', '#f0abfc'], 'prism', '#fdf4ff'),
            'retail-promo-portrait' => self::theme(['#330b45', '#a21caf', '#f5d0fe'], 'rays', '#fdf4ff'),
            'new-arrivals' => self::theme(['#1b1035', '#6d28d9', '#c4b5fd'], 'bokeh', '#f5f3ff'),
            'flash-sale' => self::theme(['#3b0712', '#be123c', '#fda4af'], 'rays', '#fff1f2'),
            'square-retail-sale' => self::theme(['#420a18', '#e11d48', '#fecdd3'], 'prism', '#fff1f2'),
            'square-product-feature' => self::theme(['#111827', '#374151', '#d1d5db'], 'mesh', '#f9fafb'),
            'square-brand-spotlight' => self::theme(['#0b1120', '#1e293b', '#94a3b8'], 'arcs', '#f8fafc'),

            // Education.
            'education-campus' => self::theme(['#15243f', '#2563eb', '#bfdbfe'], 'contour', '#eff6ff'),
            'exam-schedule' => self::theme(['#111c33', '#3730a3', '#a5b4fc'], 'grid', '#eef2ff'),
            'campus-events-board' => self::theme(['#1a1433', '#5b21b6', '#ddd6fe'], 'waves', '#f5f3ff'),

            // Hospitality and property.
            'hospitality-welcome' => self::theme(['#21160c', '#a16207', '#fde68a'], 'arcs', '#fffbeb'),
            'hotel-amenities' => self::theme(['#1a1a10', '#78716c', '#e7e5e4'], 'contour', '#fafaf9'),
            'portrait-hotel-amenities' => self::theme(['#1c1917', '#57534e', '#d6d3d1'], 'mesh', '#fafaf9'),
            'guest-check-in' => self::theme(['#0f172a', '#334155', '#cbd5e1'], 'waves', '#f8fafc'),
            'property-showcase' => self::theme(['#0c1f1a', '#047857', '#6ee7b7'], 'contour', '#ecfdf5'),
            'portrait-property-listing' => self::theme(['#08201b', '#059669', '#a7f3d0'], 'arcs', '#ecfdf5'),

            // Events.
            'events-agenda' => self::theme(['#1c0f2e', '#7e22ce', '#e9d5ff'], 'rays', '#faf5ff'),
            'event-countdown' => self::theme(['#2b0b2e', '#a21caf', '#f0abfc'], 'prism', '#fdf4ff'),
            'square-event-teaser' => self::theme(['#230d33', '#7c3aed', '#ddd6fe'], 'bokeh', '#f5f3ff'),
            'sponsor-showcase' => self::theme(['#0f1729', '#1e40af', '#bfdbfe'], 'grid', '#eff6ff'),
            'portrait-sponsor-list' => self::theme(['#0b1220', '#1d4ed8', '#93c5fd'], 'mesh', '#eff6ff'),

            // Transport.
            'transport-departures' => self::theme(['#06131f', '#0f766e', '#5eead4'], 'grid', '#f0fdfa'),
            'transit-departures-table' => self::theme(['#050f1a', '#115e59', '#2dd4bf'], 'contour', '#ccfbf1'),

            // News, social and media.
            'news-rss-wall' => self::theme(['#0b0f1a', '#1e293b', '#60a5fa'], 'contour', '#e2e8f0'),
            'social-community-wall' => self::theme(['#180f2b', '#7c3aed', '#c4b5fd'], 'bokeh', '#f5f3ff'),
            'square-social-quote' => self::theme(['#1e1b4b', '#4338ca', '#c7d2fe'], 'waves', '#eef2ff'),
            'media-lounge' => self::theme(['#0a0a0a', '#262626', '#a3a3a3'], 'prism', '#fafafa'),
            'weather-world-clock' => self::theme(['#082032', '#0369a1', '#7dd3fc'], 'waves', '#f0f9ff'),

            // Notices and wayfinding.
            'announcements-notice' => self::theme(['#132030', '#1d4ed8', '#93c5fd'], 'mesh', '#eff6ff'),
            'emergency-notice' => self::theme(['#3f0a0a', '#b91c1c', '#fca5a5'], 'rays', '#fef2f2'),
            'portrait-wayfinding' => self::theme(['#0a1f33', '#0369a1', '#7dd3fc'], 'arcs', '#f0f9ff'),

            // Image-rich showcase layouts.
            ...array_map(
                fn (array $theme): array => self::theme(...$theme),
                CatalogShowcaseTemplates::artwork(),
            ),
        ];
    }

    /**
     * Public path a template's `src` should point at.
     */
    public static function source(string $key): string
    {
        return '/'.self::DIRECTORY.'/'.$key.'.svg';
    }

    /**
     * Render one artwork as a standalone SVG document.
     */
    public static function svg(string $key, int $width = 1600, int $height = 900): string
    {
        $theme = self::themes()[$key] ?? self::theme(['#0f172a', '#334155', '#94a3b8'], 'mesh', '#f8fafc');
        $random = self::seed($key);
        [$dark, $mid, $light] = $theme['colors'];
        $id = preg_replace('/[^a-z0-9]+/i', '', $key) ?: 'art';

        $defs = <<<SVG
    <linearGradient id="base{$id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$dark}"/>
      <stop offset="0.55" stop-color="{$mid}"/>
      <stop offset="1" stop-color="{$dark}"/>
    </linearGradient>
    <radialGradient id="glow{$id}" cx="0.72" cy="0.22" r="0.85">
      <stop offset="0" stop-color="{$light}" stop-opacity="0.55"/>
      <stop offset="1" stop-color="{$light}" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="vig{$id}" cx="0.5" cy="0.5" r="0.75">
      <stop offset="0.55" stop-color="#000000" stop-opacity="0"/>
      <stop offset="1" stop-color="#000000" stop-opacity="0.45"/>
    </radialGradient>
SVG;

        $motif = self::motif($theme['motif'], $width, $height, $light, $theme['accent'], $random);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="{$width}" height="{$height}" preserveAspectRatio="xMidYMid slice" role="img" aria-label="{$key} background">
  <defs>
{$defs}
  </defs>
  <rect width="{$width}" height="{$height}" fill="url(#base{$id})"/>
  <rect width="{$width}" height="{$height}" fill="url(#glow{$id})"/>
{$motif}
  <rect width="{$width}" height="{$height}" fill="url(#vig{$id})"/>
</svg>

SVG;
    }

    /**
     * Deterministic pseudo-random generator so regenerating never churns files.
     *
     * @return callable(float, float): float
     */
    private static function seed(string $key): callable
    {
        $state = crc32($key) ?: 1;

        return function (float $min, float $max) use (&$state): float {
            // xorshift32 keeps the sequence stable across PHP versions.
            $state ^= ($state << 13) & 0xFFFFFFFF;
            $state ^= $state >> 17;
            $state ^= ($state << 5) & 0xFFFFFFFF;
            $state &= 0xFFFFFFFF;

            return $min + (($state / 0xFFFFFFFF) * ($max - $min));
        };
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function motif(
        string $motif,
        int $width,
        int $height,
        string $light,
        string $accent,
        callable $random,
    ): string {
        return match ($motif) {
            'rays' => self::rays($width, $height, $light, $accent, $random),
            'waves' => self::waves($width, $height, $light, $accent, $random),
            'grid' => self::grid($width, $height, $light, $accent),
            'arcs' => self::arcs($width, $height, $light, $accent, $random),
            'bokeh' => self::bokeh($width, $height, $light, $accent, $random),
            'prism' => self::prism($width, $height, $light, $accent, $random),
            'contour' => self::contour($width, $height, $light, $accent, $random),
            default => self::mesh($width, $height, $light, $accent, $random),
        };
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function mesh(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];

        for ($i = 0; $i < 7; $i++) {
            $x1 = round($random(0, $width), 1);
            $y1 = round($random(0, $height), 1);
            $x2 = round($random(0, $width), 1);
            $y2 = round($random(0, $height), 1);
            $x3 = round($random(0, $width), 1);
            $y3 = round($random(0, $height), 1);
            $fill = $i % 2 === 0 ? $light : $accent;
            $opacity = round($random(0.05, 0.16), 3);
            $shapes[] = "  <polygon points=\"{$x1},{$y1} {$x2},{$y2} {$x3},{$y3}\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function rays(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];
        $originX = round($width * $random(0.6, 0.9), 1);
        $originY = round($height * $random(-0.15, 0.1), 1);

        for ($i = 0; $i < 11; $i++) {
            $spread = $width * 0.24;
            $baseX = round(-$width * 0.3 + ($i * $spread), 1);
            $endX = round($baseX + $spread * 0.42, 1);
            $fill = $i % 2 === 0 ? $light : $accent;
            $opacity = round($random(0.04, 0.12), 3);
            $shapes[] = "  <polygon points=\"{$originX},{$originY} {$baseX},{$height} {$endX},{$height}\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function waves(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];

        for ($i = 0; $i < 6; $i++) {
            $base = round($height * (0.28 + ($i * 0.13)), 1);
            $amplitude = round($random($height * 0.04, $height * 0.11), 1);
            $control1 = round($base - $amplitude, 1);
            $control2 = round($base + $amplitude, 1);
            $fill = $i % 2 === 0 ? $light : $accent;
            $opacity = round($random(0.05, 0.13), 3);
            $half = round($width / 2, 1);
            $shapes[] = "  <path d=\"M0,{$base} C{$half},{$control1} {$half},{$control2} {$width},{$base} L{$width},{$height} L0,{$height} Z\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
        }

        return implode("\n", $shapes);
    }

    private static function grid(int $width, int $height, string $light, string $accent): string
    {
        $shapes = [];
        $step = (int) round($width / 22);

        for ($x = $step; $x < $width; $x += $step) {
            $shapes[] = "  <line x1=\"{$x}\" y1=\"0\" x2=\"{$x}\" y2=\"{$height}\" stroke=\"{$light}\" stroke-opacity=\"0.09\" stroke-width=\"2\"/>";
        }

        for ($y = $step; $y < $height; $y += $step) {
            $shapes[] = "  <line x1=\"0\" y1=\"{$y}\" x2=\"{$width}\" y2=\"{$y}\" stroke=\"{$accent}\" stroke-opacity=\"0.06\" stroke-width=\"2\"/>";
        }

        $band = (int) round($height * 0.34);
        $shapes[] = "  <rect x=\"0\" y=\"{$band}\" width=\"{$width}\" height=\"".(int) round($height * 0.16)."\" fill=\"{$light}\" opacity=\"0.07\"/>";

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function arcs(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];
        $cx = round($width * $random(0.15, 0.4), 1);
        $cy = round($height * $random(0.6, 0.95), 1);

        for ($i = 1; $i <= 9; $i++) {
            $radius = round(($width / 12) * $i * $random(0.9, 1.15), 1);
            $stroke = $i % 2 === 0 ? $light : $accent;
            $opacity = round(0.2 - ($i * 0.016), 3);
            $shapes[] = "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$radius}\" fill=\"none\" stroke=\"{$stroke}\" stroke-opacity=\"{$opacity}\" stroke-width=\"".max(2, (int) round($width / 480)).'"/>';
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function bokeh(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];

        for ($i = 0; $i < 16; $i++) {
            $cx = round($random(0, $width), 1);
            $cy = round($random(0, $height), 1);
            $radius = round($random($width * 0.02, $width * 0.11), 1);
            $fill = $i % 3 === 0 ? $accent : $light;
            $opacity = round($random(0.04, 0.15), 3);
            $shapes[] = "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$radius}\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function prism(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];
        $slices = 9;
        $sliceWidth = $width / $slices;

        for ($i = 0; $i < $slices; $i++) {
            $x = round($i * $sliceWidth, 1);
            $skew = round($sliceWidth * $random(0.3, 1.1), 1);
            $right = round($x + $sliceWidth, 1);
            $fill = $i % 2 === 0 ? $light : $accent;
            $opacity = round($random(0.04, 0.13), 3);
            $shapes[] = "  <polygon points=\"{$x},0 {$right},0 ".round($right - $skew, 1).",{$height} ".round($x - $skew, 1).",{$height}\" fill=\"{$fill}\" opacity=\"{$opacity}\"/>";
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  callable(float, float): float  $random
     */
    private static function contour(int $width, int $height, string $light, string $accent, callable $random): string
    {
        $shapes = [];

        for ($i = 0; $i < 10; $i++) {
            $y = round($height * ($i / 10) + $random(-20, 20), 1);
            $c1 = round($y + $random(-$height * 0.12, $height * 0.12), 1);
            $c2 = round($y + $random(-$height * 0.12, $height * 0.12), 1);
            $third = round($width / 3, 1);
            $twoThirds = round($width * 2 / 3, 1);
            $stroke = $i % 2 === 0 ? $light : $accent;
            $opacity = round($random(0.07, 0.18), 3);
            $shapes[] = "  <path d=\"M0,{$y} C{$third},{$c1} {$twoThirds},{$c2} {$width},{$y}\" fill=\"none\" stroke=\"{$stroke}\" stroke-opacity=\"{$opacity}\" stroke-width=\"".max(2, (int) round($width / 500)).'"/>';
        }

        return implode("\n", $shapes);
    }

    /**
     * @param  list<string>  $colors
     * @return array{colors: list<string>, motif: string, accent: string}
     */
    private static function theme(array $colors, string $motif, string $accent): array
    {
        return [
            'colors' => $colors,
            'motif' => in_array($motif, self::MOTIFS, true) ? $motif : 'mesh',
            'accent' => $accent,
        ];
    }
}
