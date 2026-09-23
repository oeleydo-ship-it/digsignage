<?php

namespace App\Services\Template;

/**
 * Renders a design document to a GD image for template thumbnails.
 */
class RenderTemplateCanvas
{
    protected ?string $fontPath = null;

    /**
     * @param  array{width: int, height: int, background: string, elements: list<array<string, mixed>>}  $document
     * @return \GdImage|null
     */
    public function render(array $document, int $maxWidth = 480, int $maxHeight = 270): mixed
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $sourceWidth = max(1, (int) $document['width']);
        $sourceHeight = max(1, (int) $document['height']);
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            return null;
        }

        $background = is_string($document['background'] ?? null) ? $document['background'] : '#111827';
        imagefilledrectangle(
            $canvas,
            0,
            0,
            $width,
            $height,
            $this->color($canvas, $background),
        );

        $elements = $document['elements'];
        usort($elements, fn (array $a, array $b): int => ((int) ($a['zIndex'] ?? 0)) <=> ((int) ($b['zIndex'] ?? 0)));

        foreach ($elements as $element) {
            if (($element['hidden'] ?? false) === true) {
                continue;
            }

            $this->drawElement($canvas, $element, $scale);
        }

        return $canvas;
    }

    /**
     * @param  \GdImage  $canvas
     * @param  array<string, mixed>  $element
     */
    protected function drawElement(mixed $canvas, array $element, float $scale): void
    {
        $type = (string) ($element['type'] ?? '');
        $props = is_array($element['props'] ?? null) ? $element['props'] : [];
        $x = (int) round(((float) ($element['x'] ?? 0)) * $scale);
        $y = (int) round(((float) ($element['y'] ?? 0)) * $scale);
        $width = max(1, (int) round(((float) ($element['width'] ?? 8)) * $scale));
        $height = max(1, (int) round(((float) ($element['height'] ?? 8)) * $scale));
        $opacity = max(0.0, min(1.0, (float) ($element['opacity'] ?? 1)));

        if ($type === 'shape') {
            $fill = is_string($props['fill'] ?? null) ? $props['fill'] : '#2563eb';
            $this->filledRect($canvas, $x, $y, $width, $height, $fill, $opacity);

            return;
        }

        if ($type === 'image' || $type === 'logo') {
            $src = is_string($props['src'] ?? null) ? $props['src'] : '';
            if ($src !== '' && $this->drawImage($canvas, $src, $x, $y, $width, $height)) {
                return;
            }

            $this->filledRect($canvas, $x, $y, $width, $height, '#334155', $opacity);

            return;
        }

        if ($type === 'text') {
            $this->drawTextBlock(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['text'] ?? ''),
                max(8, (int) round(((int) ($props['fontSize'] ?? 24)) * $scale)),
                is_string($props['color'] ?? null) ? $props['color'] : '#ffffff',
                (string) ($props['align'] ?? 'left'),
                (string) ($props['fontWeight'] ?? '400'),
            );

            return;
        }

        if ($type === 'ticker') {
            $background = is_string($props['background'] ?? null) ? $props['background'] : '#0f172a';
            if ($background !== 'transparent') {
                $this->filledRect($canvas, $x, $y, $width, $height, $background, 1);
            }

            $this->drawTextBlock(
                $canvas,
                $x + 4,
                $y,
                $width - 8,
                $height,
                (string) ($props['text'] ?? ''),
                max(8, (int) round(((int) ($props['fontSize'] ?? 48)) * $scale)),
                is_string($props['color'] ?? null) ? $props['color'] : '#ffffff',
                'left',
                '600',
                true,
            );

            return;
        }

        if ($this->isWidgetType($type)) {
            $this->drawWidget($canvas, $type, $props, $x, $y, $width, $height, $scale);

            return;
        }

        $this->filledRect($canvas, $x, $y, $width, $height, '#1e293b', $opacity);
    }

    protected function isWidgetType(string $type): bool
    {
        return in_array($type, [
            'clock', 'date', 'weather', 'rss', 'news', 'qr_code', 'web_page', 'youtube',
            'calendar', 'countdown', 'menu_board', 'alert_banner', 'table', 'booking',
            'room_info', 'social_wall', 'world_clock', 'charts', 'json_api',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  \GdImage  $canvas
     */
    protected function drawWidget(
        mixed $canvas,
        string $type,
        array $props,
        int $x,
        int $y,
        int $width,
        int $height,
        float $scale,
    ): void {
        $color = is_string($props['color'] ?? null) ? $props['color'] : '#ffffff';
        $fontSize = max(8, (int) round(((int) ($props['fontSize'] ?? 24)) * $scale));

        match ($type) {
            'clock' => $this->drawHeroWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                'Clock',
                now()->format(match ((string) ($props['format'] ?? 'HH:mm')) {
                    'h:mm a' => 'g:i A',
                    'HH:mm:ss' => 'H:i:s',
                    default => 'H:i',
                }),
                $color,
                max(12, (int) round($fontSize * 1.1)),
            ),
            'date' => $this->drawHeroWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                'Date',
                now()->format('l, F j'),
                $color,
                max(10, (int) round($fontSize * 0.85)),
            ),
            'weather' => $this->drawHeroWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['location'] ?? 'Weather'),
                '24°C',
                $color,
                max(14, (int) round($fontSize * 1.2)),
            ),
            'countdown' => $this->drawHeroWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['label'] ?? 'Countdown'),
                '12:34:56',
                $color,
                max(12, (int) round($fontSize * 1.1)),
            ),
            'qr_code' => $this->drawQrPlaceholder($canvas, $x, $y, $width, $height),
            'web_page' => $this->drawWebPagePlaceholder($canvas, $x, $y, $width, $height, (string) ($props['url'] ?? '')),
            'youtube' => $this->drawYoutubePlaceholder($canvas, $x, $y, $width, $height),
            'news', 'rss' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                $type === 'news' ? 'News' : 'Headlines',
                $this->demoHeadlines(),
                $color,
                $fontSize,
            ),
            'calendar' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['heading'] ?? 'Calendar'),
                $this->parseLines($props['events'] ?? ''),
                $color,
                $fontSize,
            ),
            'menu_board' => $this->drawMenuBoard($canvas, $x, $y, $width, $height, $props, $scale),
            'alert_banner' => $this->drawAlertBanner($canvas, $x, $y, $width, $height, $props, $scale),
            'table' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['heading'] ?? 'Table'),
                $this->parseTableRows($props['rows'] ?? ''),
                $color,
                $fontSize,
            ),
            'booking' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['heading'] ?? 'Bookings'),
                $this->parseBookingLines($props['bookings'] ?? ''),
                $color,
                $fontSize,
            ),
            'room_info' => $this->drawRoomInfo($canvas, $x, $y, $width, $height, $props, $scale),
            'social_wall' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                (string) ($props['heading'] ?? 'Posts'),
                $this->parseLines($props['posts'] ?? ''),
                $color,
                $fontSize,
            ),
            'world_clock' => $this->drawListWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                'World clocks',
                $this->parseWorldClockLines($props['cities'] ?? ''),
                $color,
                $fontSize,
            ),
            'charts' => $this->drawChartWidget($canvas, $x, $y, $width, $height, $props, $scale),
            'json_api' => $this->drawHeroWidget(
                $canvas,
                $x,
                $y,
                $width,
                $height,
                'Live metric',
                'Sample headline',
                $color,
                max(12, (int) round($fontSize * 1.1)),
            ),
            default => $this->filledRect($canvas, $x, $y, $width, $height, '#1e293b', 1),
        };
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawMenuBoard(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        array $props,
        float $scale,
    ): void {
        $this->filledRect($canvas, $x, $y, $width, $height, '#292524', 1);
        $color = is_string($props['color'] ?? null) ? $props['color'] : '#fff7ed';
        $fontSize = max(8, (int) round(((int) ($props['fontSize'] ?? 36)) * $scale));
        $heading = (string) ($props['heading'] ?? 'Menu');
        $currency = (string) ($props['currency'] ?? '');
        $lines = [];

        foreach ($this->parseLines($props['items'] ?? '') as $line) {
            $parts = array_map(trim(...), explode('|', $line));
            $name = $parts[0] ?? '';
            $price = $parts[1] ?? '';
            $lines[] = $price !== '' && $currency !== ''
                ? "{$name}  {$currency} {$price}"
                : $name;
        }

        array_unshift($lines, $heading);

        $this->drawListWidget($canvas, $x, $y, $width, $height, '', $lines, $color, $fontSize);
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawAlertBanner(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        array $props,
        float $scale,
    ): void {
        $severity = (string) ($props['severity'] ?? 'info');
        $background = match ($severity) {
            'critical' => '#991b1b',
            'warning' => '#b45309',
            default => '#1d4ed8',
        };

        $this->filledRect($canvas, $x, $y, $width, $height, $background, 1);
        $fontSize = max(8, (int) round(((int) ($props['fontSize'] ?? 36)) * $scale));
        $heading = (string) ($props['heading'] ?? 'Notice');
        $message = (string) ($props['message'] ?? '');
        $color = is_string($props['color'] ?? null) ? $props['color'] : '#ffffff';

        $this->drawTextBlock($canvas, $x + 8, $y + 8, $width - 16, (int) ($height * 0.35), $heading, (int) ($fontSize * 1.1), $color, 'left', '700');
        $this->drawTextBlock($canvas, $x + 8, $y + (int) ($height * 0.35), $width - 16, (int) ($height * 0.6), $message, $fontSize, $color, 'left', '400');
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawRoomInfo(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        array $props,
        float $scale,
    ): void {
        $this->filledRect($canvas, $x, $y, $width, $height, '#ffffff', 1);
        $color = is_string($props['color'] ?? null) ? $props['color'] : '#0f172a';
        $fontSize = max(8, (int) round(((int) ($props['fontSize'] ?? 40)) * $scale));
        $room = (string) ($props['room_name'] ?? 'Room');
        $status = (string) ($props['status'] ?? 'available');
        $next = (string) ($props['next_meeting'] ?? '');

        $this->drawTextBlock($canvas, $x + 8, $y + 8, $width - 16, (int) ($height * 0.35), $room, (int) ($fontSize * 1.1), $color, 'left', '700');
        $this->drawTextBlock($canvas, $x + 8, $y + (int) ($height * 0.38), $width - 16, (int) ($height * 0.25), ucfirst($status), $fontSize, $color, 'left', '600');
        $this->drawTextBlock($canvas, $x + 8, $y + (int) ($height * 0.62), $width - 16, (int) ($height * 0.3), $next, (int) ($fontSize * 0.75), $color, 'left', '400');
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawChartWidget(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        array $props,
        float $scale,
    ): void {
        $this->filledRect($canvas, $x, $y, $width, $height, '#111827', 1);
        $title = (string) ($props['title'] ?? 'Chart');
        $color = is_string($props['color'] ?? null) ? $props['color'] : '#e5e7eb';
        $this->drawTextBlock($canvas, $x + 8, $y + 8, $width - 16, 20, $title, max(8, (int) round(14 * $scale)), $color, 'left', '600');

        $series = $this->parseChartSeries($props['series'] ?? '');
        $barAreaY = $y + 28;
        $barAreaHeight = max(10, $height - 36);
        $max = max(1, ...array_column($series, 'value'));
        $count = max(1, count($series));
        $gap = 4;
        $barWidth = max(4, (int) (($width - 16 - ($gap * ($count - 1))) / $count));

        foreach ($series as $index => $item) {
            $barHeight = max(2, (int) round(($item['value'] / $max) * $barAreaHeight));
            $barX = $x + 8 + ($index * ($barWidth + $gap));
            $barY = $barAreaY + $barAreaHeight - $barHeight;
            imagefilledrectangle($canvas, $barX, $barY, $barX + $barWidth, $barAreaY + $barAreaHeight, $this->color($canvas, '#3b82f6'));
        }
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawHeroWidget(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        string $label,
        string $value,
        string $color,
        int $valueSize,
    ): void {
        $this->drawTextBlock($canvas, $x + 6, $y + 4, $width - 12, max(10, (int) ($height * 0.25)), $label, max(7, (int) ($valueSize * 0.45)), $color, 'left', '400');
        $this->drawTextBlock($canvas, $x + 6, $y + (int) ($height * 0.28), $width - 12, (int) ($height * 0.65), $value, $valueSize, $color, 'left', '700');
    }

    /**
     * @param  list<string>  $lines
     * @param  \GdImage  $canvas
     */
    protected function drawListWidget(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        string $heading,
        array $lines,
        string $color,
        int $fontSize,
    ): void {
        $padding = 8;
        $cursorY = $y + $padding;

        if ($heading !== '') {
            $this->drawTextBlock($canvas, $x + $padding, $cursorY, $width - ($padding * 2), max(10, (int) ($fontSize * 1.2)), $heading, max(8, (int) ($fontSize * 0.65)), $color, 'left', '600');
            $cursorY += max(12, (int) ($fontSize * 1.1));
        }

        $lineHeight = max(10, (int) ($fontSize * 1.15));
        $maxLines = max(1, (int) floor(($y + $height - $cursorY) / $lineHeight));

        foreach (array_slice($lines, 0, $maxLines) as $line) {
            $this->drawTextBlock($canvas, $x + $padding, $cursorY, $width - ($padding * 2), $lineHeight, $line, $fontSize, $color, 'left', '400');
            $cursorY += $lineHeight;
        }
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawQrPlaceholder(mixed $canvas, int $x, int $y, int $width, int $height): void
    {
        $this->filledRect($canvas, $x, $y, $width, $height, '#ffffff', 1);
        $size = min($width, $height) - 12;
        $left = $x + (int) (($width - $size) / 2);
        $top = $y + (int) (($height - $size) / 2);
        $cells = 8;
        $cell = max(2, (int) floor($size / $cells));

        for ($row = 0; $row < $cells; $row++) {
            for ($col = 0; $col < $cells; $col++) {
                if (($row + $col) % 2 === 0) {
                    imagefilledrectangle(
                        $canvas,
                        $left + ($col * $cell),
                        $top + ($row * $cell),
                        $left + (($col + 1) * $cell) - 1,
                        $top + (($row + 1) * $cell) - 1,
                        $this->color($canvas, '#111827'),
                    );
                }
            }
        }
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawWebPagePlaceholder(mixed $canvas, int $x, int $y, int $width, int $height, string $url): void
    {
        $this->filledRect($canvas, $x, $y, $width, $height, '#e2e8f0', 1);
        $this->filledRect($canvas, $x, $y, $width, max(8, (int) ($height * 0.12)), '#cbd5e1', 1);
        $this->drawTextBlock($canvas, $x + 6, $y + max(8, (int) ($height * 0.35)), $width - 12, (int) ($height * 0.5), $url !== '' ? $url : 'Web page', 9, '#475569', 'left', '400');
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawYoutubePlaceholder(mixed $canvas, int $x, int $y, int $width, int $height): void
    {
        $this->filledRect($canvas, $x, $y, $width, $height, '#0f172a', 1);
        $playSize = min($width, $height) / 4;
        $cx = $x + (int) ($width / 2);
        $cy = $y + (int) ($height / 2);
        $points = [
            $cx - (int) ($playSize / 2),
            $cy - (int) ($playSize / 2),
            $cx - (int) ($playSize / 2),
            $cy + (int) ($playSize / 2),
            $cx + (int) ($playSize / 2),
            $cy,
        ];
        imagefilledpolygon($canvas, $points, $this->color($canvas, '#ef4444'));
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawImage(mixed $canvas, string $src, int $x, int $y, int $width, int $height): bool
    {
        $path = $this->resolveImagePath($src);

        if ($path === null || ! is_file($path)) {
            return false;
        }

        $source = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => @imagecreatefromjpeg($path),
        };

        if ($source === false) {
            return false;
        }

        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);

        return true;
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function drawTextBlock(
        mixed $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        string $text,
        int $fontSize,
        string $color,
        string $align,
        string $weight,
        bool $singleLine = false,
    ): void {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $font = $this->fontPath();
        $textColor = $this->color($canvas, $color);

        if ($font !== null && function_exists('imagettftext')) {
            $lines = $singleLine ? [$text] : $this->wrapText($text, $font, $fontSize, $width);
            $lineHeight = (int) round($fontSize * 1.2);
            $cursorY = $y + $fontSize;

            foreach ($lines as $line) {
                if ($cursorY > $y + $height) {
                    break;
                }

                $box = imagettfbbox($fontSize, 0, $font, $line);
                $lineWidth = is_array($box) ? abs($box[2] - $box[0]) : 0;
                $drawX = match ($align) {
                    'center' => $x + (int) max(0, ($width - $lineWidth) / 2),
                    'right' => $x + max(0, $width - $lineWidth),
                    default => $x,
                };

                imagettftext($canvas, $fontSize, 0, $drawX, $cursorY, $textColor, $font, $line);
                $cursorY += $lineHeight;
            }

            return;
        }

        imagestring($canvas, $weight === '700' ? 5 : 3, $x, $y, substr($text, 0, 80), $textColor);
    }

    /**
     * @return list<string>
     */
    protected function wrapText(string $text, string $font, int $fontSize, int $maxWidth): array
    {
        $lines = [];
        $paragraphs = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            $current = '';

            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;
                $box = imagettfbbox($fontSize, 0, $font, $candidate);
                $width = is_array($box) ? abs($box[2] - $box[0]) : 0;

                if ($width > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines === [] ? [$text] : $lines;
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function filledRect(mixed $canvas, int $x, int $y, int $width, int $height, string $fill, float $opacity): void
    {
        if ($opacity >= 0.999) {
            imagefilledrectangle($canvas, $x, $y, $x + $width, $y + $height, $this->color($canvas, $fill));

            return;
        }

        $overlay = imagecreatetruecolor($width, $height);

        if ($overlay === false) {
            return;
        }

        imagefilledrectangle($overlay, 0, 0, $width, $height, $this->color($overlay, $fill));
        imagecopymerge($canvas, $overlay, $x, $y, 0, 0, $width, $height, (int) round($opacity * 100));
        imagedestroy($overlay);
    }

    protected function resolveImagePath(string $src): ?string
    {
        if (str_starts_with($src, '/')) {
            return public_path(ltrim($src, '/'));
        }

        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return null;
        }

        return public_path($src);
    }

    protected function fontPath(): ?string
    {
        if ($this->fontPath !== null) {
            return $this->fontPath !== '' ? $this->fontPath : null;
        }

        foreach ([
            resource_path('fonts/Arial.ttf'),
            resource_path('fonts/DejaVuSans.ttf'),
            'C:\\Windows\\Fonts\\arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $this->fontPath = $candidate;
            }
        }

        return $this->fontPath = '';
    }

    /**
     * @return list<string>
     */
    protected function parseLines(mixed $value): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\r\n|\r|\n/', (string) $value) ?: [])));
    }

    /**
     * @return list<string>
     */
    protected function parseTableRows(mixed $value): array
    {
        return $this->parseLines($value);
    }

    /**
     * @return list<string>
     */
    protected function parseBookingLines(mixed $value): array
    {
        $lines = [];

        foreach ($this->parseLines($value) as $line) {
            $parts = array_map(trim(...), explode('|', $line));
            $when = $parts[0] ?? '';
            $title = $parts[1] ?? $when;
            $lines[] = trim($when.' · '.$title);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected function parseWorldClockLines(mixed $value): array
    {
        $lines = [];

        foreach ($this->parseLines($value) as $line) {
            $parts = array_map(trim(...), explode('|', $line));
            $city = $parts[0] ?? 'City';
            $lines[] = $city.'  '.now()->format('H:i');
        }

        return $lines;
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    protected function parseChartSeries(mixed $value): array
    {
        $series = [];

        foreach ($this->parseLines($value) as $line) {
            [$label, $amount] = array_pad(explode(':', $line, 2), 2, '0');
            $series[] = [
                'label' => trim($label),
                'value' => max(0, (int) trim($amount)),
            ];
        }

        return $series === [] ? [['label' => 'A', 'value' => 10]] : $series;
    }

    /**
     * @return list<string>
     */
    protected function demoHeadlines(): array
    {
        return [
            'Markets steady ahead of policy update',
            'City opens new transit hub downtown',
            'Community festival returns this weekend',
        ];
    }

    /**
     * @param  \GdImage  $canvas
     */
    protected function color(mixed $canvas, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if ($hex === 'transparent') {
            return imagecolorallocate($canvas, 17, 24, 39);
        }

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '111827';
        }

        $color = imagecolorallocate(
            $canvas,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );

        return $color === false ? 0 : $color;
    }
}
