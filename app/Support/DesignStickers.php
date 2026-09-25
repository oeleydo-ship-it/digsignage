<?php

namespace App\Support;

/**
 * Decorative stickers for the designer's graphics library.
 *
 * Each sticker is a self-contained SVG with its own text, so it drops onto a
 * canvas as a normal image element and scales cleanly on any screen.
 */
final class DesignStickers
{
    public const DIRECTORY = 'images/library/stickers';

    private const FONT = "font-family=\"'Open Sans','Segoe UI',Arial,sans-serif\"";

    /**
     * @return array<string, array{label: string, width: int, height: int}>
     */
    public static function catalog(): array
    {
        return [
            'sale-burst' => ['label' => 'Sale burst', 'width' => 400, 'height' => 400],
            'percent-off' => ['label' => '% off burst', 'width' => 400, 'height' => 400],
            'new-badge' => ['label' => 'New badge', 'width' => 360, 'height' => 360],
            'best-seller' => ['label' => 'Best seller seal', 'width' => 400, 'height' => 400],
            'today-only' => ['label' => 'Today only ribbon', 'width' => 720, 'height' => 200],
            'limited-offer' => ['label' => 'Limited offer ribbon', 'width' => 720, 'height' => 200],
            'now-open' => ['label' => 'Now open sign', 'width' => 640, 'height' => 280],
            'now-hiring' => ['label' => 'Now hiring tag', 'width' => 640, 'height' => 260],
            'welcome' => ['label' => 'Welcome script', 'width' => 800, 'height' => 260],
            'free-wifi' => ['label' => 'Free Wi-Fi pill', 'width' => 560, 'height' => 160],
            'vegan' => ['label' => 'Vegan leaf', 'width' => 320, 'height' => 320],
            'spicy' => ['label' => 'Spicy chilli', 'width' => 320, 'height' => 320],
            'arrow-callout' => ['label' => 'Arrow callout', 'width' => 640, 'height' => 220],
            'star-rating' => ['label' => 'Five stars', 'width' => 640, 'height' => 140],
            'quote-mark' => ['label' => 'Quote mark', 'width' => 300, 'height' => 240],
            'wave-divider' => ['label' => 'Wave divider', 'width' => 1920, 'height' => 180],
        ];
    }

    public static function source(string $key): string
    {
        return '/'.self::DIRECTORY.'/'.$key.'.svg';
    }

    public static function svg(string $key): string
    {
        $meta = self::catalog()[$key] ?? ['label' => $key, 'width' => 400, 'height' => 400];
        $w = $meta['width'];
        $h = $meta['height'];
        $font = self::FONT;

        $body = match ($key) {
            'sale-burst' => self::burst($w, $h, '#e11d48', '#fff1f2', [['SALE', 92, 0]]),
            'percent-off' => self::burst($w, $h, '#f59e0b', '#1c1917', [['50%', 104, -14], ['OFF', 56, 58]]),
            'new-badge' => implode("\n", [
                '  <circle cx="180" cy="180" r="170" fill="#16a34a"/>',
                '  <circle cx="180" cy="180" r="150" fill="none" stroke="#dcfce7" stroke-width="6" stroke-dasharray="4 12" stroke-linecap="round"/>',
                "  <text x=\"180\" y=\"212\" text-anchor=\"middle\" {$font} font-size=\"96\" font-weight=\"800\" fill=\"#ffffff\">NEW</text>",
            ]),
            'best-seller' => self::seal($w, $h),
            'today-only' => self::ribbon($w, $h, '#dc2626', '#991b1b', 'TODAY ONLY'),
            'limited-offer' => self::ribbon($w, $h, '#7c3aed', '#5b21b6', 'LIMITED OFFER'),
            'now-open' => implode("\n", [
                '  <line x1="120" y1="0" x2="220" y2="70" stroke="#94a3b8" stroke-width="6"/>',
                '  <line x1="520" y1="0" x2="420" y2="70" stroke="#94a3b8" stroke-width="6"/>',
                '  <circle cx="320" cy="14" r="12" fill="#94a3b8"/>',
                '  <rect x="20" y="70" width="600" height="190" rx="28" fill="#0f172a" stroke="#22c55e" stroke-width="10"/>',
                "  <text x=\"320\" y=\"196\" text-anchor=\"middle\" {$font} font-size=\"104\" font-weight=\"800\" fill=\"#4ade80\">OPEN</text>",
            ]),
            'now-hiring' => implode("\n", [
                '  <path d="M40 20 H560 L620 130 L560 240 H40 Q20 240 20 220 V40 Q20 20 40 20 Z" fill="#ea580c"/>',
                '  <circle cx="560" cy="130" r="18" fill="#ffedd5"/>',
                "  <text x=\"290\" y=\"112\" text-anchor=\"middle\" {$font} font-size=\"56\" font-weight=\"800\" fill=\"#ffffff\">WE&#8217;RE</text>",
                "  <text x=\"290\" y=\"190\" text-anchor=\"middle\" {$font} font-size=\"76\" font-weight=\"800\" fill=\"#ffffff\">HIRING</text>",
            ]),
            'welcome' => implode("\n", [
                "  <text x=\"400\" y=\"178\" text-anchor=\"middle\" font-family=\"'Brush Script MT','Segoe Script',cursive\" font-size=\"176\" fill=\"#ffffff\">Welcome</text>",
                '  <path d="M170 214 Q400 250 630 214" fill="none" stroke="#fbbf24" stroke-width="8" stroke-linecap="round"/>',
            ]),
            'free-wifi' => implode("\n", [
                '  <rect x="0" y="0" width="560" height="160" rx="80" fill="#0ea5e9"/>',
                '  <g transform="translate(92 86)" fill="none" stroke="#ffffff" stroke-width="12" stroke-linecap="round">',
                '    <path d="M-52 -18 A74 74 0 0 1 52 -18"/>',
                '    <path d="M-32 6 A44 44 0 0 1 32 6"/>',
                '    <circle cx="0" cy="30" r="6" fill="#ffffff" stroke="none"/>',
                '  </g>',
                "  <text x=\"340\" y=\"104\" text-anchor=\"middle\" {$font} font-size=\"64\" font-weight=\"800\" fill=\"#ffffff\">FREE WI-FI</text>",
            ]),
            'vegan' => implode("\n", [
                '  <circle cx="160" cy="160" r="150" fill="#ecfccb" stroke="#65a30d" stroke-width="12"/>',
                '  <path d="M160 250 C 90 200, 80 120, 190 70 C 230 140, 220 210, 160 250 Z" fill="#65a30d"/>',
                '  <path d="M160 250 C 170 180, 180 130, 190 70" fill="none" stroke="#ecfccb" stroke-width="6" stroke-linecap="round"/>',
            ]),
            'spicy' => implode("\n", [
                '  <circle cx="160" cy="160" r="150" fill="#fee2e2" stroke="#dc2626" stroke-width="12"/>',
                '  <path d="M110 110 C 150 110, 250 150, 230 240 C 200 220, 110 200, 110 110 Z" fill="#dc2626"/>',
                '  <path d="M110 110 C 104 90, 120 70, 146 72" fill="none" stroke="#16a34a" stroke-width="12" stroke-linecap="round"/>',
            ]),
            'arrow-callout' => implode("\n", [
                '  <path d="M0 50 H480 V0 L640 110 L480 220 V170 H0 Z" fill="#fbbf24"/>',
                "  <text x=\"250\" y=\"132\" text-anchor=\"middle\" {$font} font-size=\"64\" font-weight=\"800\" fill=\"#1c1917\">THIS WAY</text>",
            ]),
            'star-rating' => self::stars($w, $h),
            'quote-mark' => '  <text x="150" y="250" text-anchor="middle" font-family="Georgia,serif" font-size="360" font-weight="700" fill="#ffffff" fill-opacity="0.9">&#8220;</text>',
            'wave-divider' => implode("\n", [
                '  <path d="M0 90 C 320 10, 640 170, 960 90 S 1600 10, 1920 90 V180 H0 Z" fill="#ffffff" fill-opacity="0.18"/>',
                '  <path d="M0 120 C 320 50, 640 190, 960 120 S 1600 50, 1920 120 V180 H0 Z" fill="#ffffff" fill-opacity="0.32"/>',
            ]),
            default => '',
        };

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$w} {$h}\" width=\"{$w}\" height=\"{$h}\" role=\"img\" aria-label=\"{$meta['label']}\">\n{$body}\n</svg>\n";
    }

    /**
     * @param  list<array{string, int, int}>  $lines  text, size, y offset from centre
     */
    private static function burst(int $w, int $h, string $fill, string $ink, array $lines): string
    {
        $cx = $w / 2;
        $cy = $h / 2;
        $points = [];

        for ($i = 0; $i < 32; $i++) {
            $radius = $i % 2 === 0 ? $w * 0.48 : $w * 0.4;
            $angle = ($i / 32) * 2 * M_PI;
            $points[] = round($cx + cos($angle) * $radius, 1).','.round($cy + sin($angle) * $radius, 1);
        }

        $out = ['  <polygon points="'.implode(' ', $points)."\" fill=\"{$fill}\"/>"];

        foreach ($lines as [$label, $size, $offset]) {
            $y = round($cy + $offset + ($size * 0.35), 1);
            $out[] = "  <text x=\"{$cx}\" y=\"{$y}\" text-anchor=\"middle\" ".self::FONT." font-size=\"{$size}\" font-weight=\"800\" fill=\"{$ink}\">{$label}</text>";
        }

        return implode("\n", $out);
    }

    private static function ribbon(int $w, int $h, string $fill, string $shadow, string $label): string
    {
        $tail = 70;
        $top = 30;
        $bottom = $h - 30;
        $mid = $h / 2;
        $right = $w - $tail;
        $notch = $w - 34;
        $bodyWidth = $w - 80;
        $bodyHeight = $h - 40;
        $centre = $w / 2;
        $baseline = $mid + 16;

        return implode("\n", [
            "  <polygon points=\"0,{$top} {$tail},{$top} {$tail},{$bottom} 0,{$bottom} 34,{$mid}\" fill=\"{$shadow}\"/>",
            "  <polygon points=\"{$w},{$top} {$w},{$bottom} {$right},{$bottom} {$right},{$top} {$notch},{$mid}\" fill=\"{$shadow}\"/>",
            "  <rect x=\"40\" y=\"10\" width=\"{$bodyWidth}\" height=\"{$bodyHeight}\" rx=\"10\" fill=\"{$fill}\"/>",
            "  <text x=\"{$centre}\" y=\"{$baseline}\" text-anchor=\"middle\" ".self::FONT." font-size=\"66\" font-weight=\"800\" letter-spacing=\"4\" fill=\"#ffffff\">{$label}</text>",
        ]);
    }

    private static function seal(int $w, int $h): string
    {
        $cx = $w / 2;
        $cy = $h / 2 - 20;
        $points = [];

        for ($i = 0; $i < 48; $i++) {
            $radius = $i % 2 === 0 ? 150 : 138;
            $angle = ($i / 48) * 2 * M_PI;
            $points[] = round($cx + cos($angle) * $radius, 1).','.round($cy + sin($angle) * $radius, 1);
        }

        $tail = fn (int $sign): string => '  <polygon points="'
            .($cx + ($sign * 90)).','.($cy + 100).' '
            .($cx + ($sign * 130)).','.($h - 10).' '
            .($cx + ($sign * 70)).','.($h - 40).' '
            .($cx + ($sign * 40)).','.($cy + 120).'" fill="#b45309"/>';

        return implode("\n", [
            $tail(-1),
            $tail(1),
            '  <polygon points="'.implode(' ', $points).'" fill="#f59e0b"/>',
            "  <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"112\" fill=\"none\" stroke=\"#fffbeb\" stroke-width=\"5\"/>",
            "  <text x=\"{$cx}\" y=\"".($cy - 6).'" text-anchor="middle" '.self::FONT.' font-size="52" font-weight="800" fill="#ffffff">BEST</text>',
            "  <text x=\"{$cx}\" y=\"".($cy + 50).'" text-anchor="middle" '.self::FONT.' font-size="44" font-weight="800" fill="#ffffff">SELLER</text>',
        ]);
    }

    private static function stars(int $w, int $h): string
    {
        $out = [];
        $size = $h * 0.5;

        for ($i = 0; $i < 5; $i++) {
            $cx = ($w / 5) * ($i + 0.5);
            $cy = $h / 2;
            $points = [];

            for ($p = 0; $p < 10; $p++) {
                $radius = $p % 2 === 0 ? $size : $size * 0.45;
                $angle = -M_PI / 2 + ($p * M_PI / 5);
                $points[] = round($cx + cos($angle) * $radius, 1).','.round($cy + sin($angle) * $radius, 1);
            }

            $out[] = '  <polygon points="'.implode(' ', $points).'" fill="#facc15"/>';
        }

        return implode("\n", $out);
    }
}
