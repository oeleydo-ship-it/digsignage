<?php

namespace App\Actions\Widget;

use App\Models\Team;
use App\Support\ContentApps;
use App\Widgets\WidgetContext;
use App\Widgets\WidgetRegistry;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ResolveWidgetData
{
    public function __construct(protected WidgetRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function handle(Team $team, string $key, array $settings, string $timezone = 'UTC', ?DateTimeInterface $at = null): array
    {
        if (! $this->registry->availableForTeam($team, $key)) {
            return [
                'key' => $key,
                'settings' => $settings,
                'data' => ['error' => __('Queue Management is not included in this plan.')],
            ];
        }

        $widget = $this->registry->find($key);

        if ($widget === null) {
            return ['error' => 'Unknown widget.'];
        }

        $at ??= now();
        $settings = ContentApps::mergeWidgetSettings($team, $key, $settings);

        try {
            $settings = $this->registry->normalizeSettings($key, $settings);
        } catch (ValidationException $exception) {
            return [
                'key' => $key,
                'settings' => $settings,
                'data' => [
                    'error' => collect($exception->errors())->flatten()->first() ?? 'Invalid widget settings.',
                ],
            ];
        }

        $context = new WidgetContext($team, $timezone, $at);
        $cacheable = ! in_array($key, ['clock', 'date', 'countdown', 'ticker'], true)
            && ! str_starts_with($key, 'queue_');

        if (! $cacheable) {
            return [
                'key' => $key,
                'settings' => $settings,
                'data' => $widget->resolve($settings, $context),
            ];
        }

        $cacheKey = 'widget:'.$team->id.':'.$key.':'.sha1((string) json_encode($settings));
        $ttl = (int) config('widgets.cache_seconds', 300);

        $data = Cache::remember($cacheKey, $ttl, fn () => $widget->resolve($settings, $context));

        return [
            'key' => $key,
            'settings' => $settings,
            'data' => $data,
        ];
    }
}
