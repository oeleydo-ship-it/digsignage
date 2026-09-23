<?php

namespace App\Support;

use App\Widgets\WidgetRegistry;
use Illuminate\Support\Str;

final class QueueBoardPresets
{
    /**
     * @return list<array{key: string, name: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'classic',
                'name' => 'Classic queue board',
                'description' => 'Large now-serving list, queue statistics, and a call ticker.',
            ],
            [
                'key' => 'media_split',
                'name' => 'Queue + media',
                'description' => 'Queue information beside a replaceable advertisement or video area.',
            ],
            [
                'key' => 'lobby',
                'name' => 'Lobby information',
                'description' => 'Now serving, waiting, join QR, clock, and weather in one layout.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::catalog(), 'key');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function document(string $preset, array $filters = []): array
    {
        $filterSettings = array_filter([
            'service_id' => isset($filters['service_id']) ? (string) $filters['service_id'] : null,
            'location_id' => isset($filters['location_id']) ? (string) $filters['location_id'] : null,
            'counter_id' => isset($filters['counter_id']) ? (string) $filters['counter_id'] : null,
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== '0');

        $elements = match ($preset) {
            'media_split' => [
                self::widget('queue_now_serving', 'Now serving', 40, 40, 660, 820, 1, $filterSettings),
                self::mediaPlaceholder(740, 40, 1140, 820, 2),
                self::widget('queue_ticker', 'Queue ticker', 40, 900, 1840, 140, 3, $filterSettings),
            ],
            'lobby' => [
                self::widget('queue_now_serving', 'Now serving', 40, 40, 1040, 690, 1, $filterSettings),
                self::widget('clock', 'Clock', 1120, 40, 760, 190, 2),
                self::widget('weather', 'Weather', 1120, 270, 360, 240, 3),
                self::widget('queue_join_qr', 'Join queue', 1520, 270, 360, 430, 4, $filterSettings),
                self::widget('queue_waiting_tickets', 'Waiting tickets', 40, 770, 1040, 270, 5, $filterSettings),
                self::widget('queue_statistics', 'Queue statistics', 1120, 740, 760, 300, 6, $filterSettings),
            ],
            default => [
                self::widget('queue_now_serving', 'Now serving', 40, 40, 1320, 760, 1, $filterSettings),
                self::widget('queue_statistics', 'Queue statistics', 1400, 40, 480, 760, 2, $filterSettings),
                self::widget('queue_ticker', 'Queue ticker', 40, 840, 1840, 200, 3, $filterSettings),
            ],
        };

        return [
            'width' => 1920,
            'height' => 1080,
            'background' => '#020617',
            'elements' => $elements,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function widget(
        string $type,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        int $zIndex,
        array $settings = [],
    ): array {
        $defaults = app(WidgetRegistry::class)->find($type)?->defaults() ?? [];

        return self::element($type, $name, $x, $y, $width, $height, $zIndex, [
            ...$defaults,
            ...$settings,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaPlaceholder(int $x, int $y, int $width, int $height, int $zIndex): array
    {
        return self::element('video', 'Advertisement / video', $x, $y, $width, $height, $zIndex, [
            'src' => null,
            'media_id' => null,
            'objectFit' => 'cover',
            'url' => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private static function element(
        string $type,
        string $name,
        int $x,
        int $y,
        int $width,
        int $height,
        int $zIndex,
        array $props,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'rotation' => 0,
            'opacity' => 1,
            'zIndex' => $zIndex,
            'locked' => false,
            'hidden' => false,
            'props' => $props,
        ];
    }
}
