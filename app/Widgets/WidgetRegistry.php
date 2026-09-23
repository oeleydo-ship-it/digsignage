<?php

namespace App\Widgets;

use App\Enums\DesignElementType;
use App\Enums\PlanFeature;
use App\Models\Location;
use App\Models\QueueCounter;
use App\Models\QueueService;
use App\Models\Team;
use App\Support\TeamQuota;
use Illuminate\Validation\ValidationException;

class WidgetRegistry
{
    /**
     * @var array<string, Widget>
     */
    protected array $widgets = [];

    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $memoizedArray = null;

    public function __construct(protected TeamQuota $quota)
    {
        foreach (WidgetCatalog::definitions() as $widget) {
            $this->widgets[$widget->key()] = $widget;
        }
    }

    public function canonicalKey(string $key): string
    {
        return match ($key) {
            'chart' => 'charts',
            'iframe' => 'web_page',
            default => $key,
        };
    }

    public function find(string $key): ?Widget
    {
        return $this->widgets[$this->canonicalKey($key)] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->widgets[$this->canonicalKey($key)]);
    }

    /**
     * @return list<Widget>
     */
    public function all(): array
    {
        return array_values($this->widgets);
    }

    /**
     * Palette entries: layout elements plus widgets not already listed.
     *
     * @return list<array{value: string, label: string}>
     */
    public function designerElementTypes(?Team $team = null): array
    {
        $types = [];
        $seen = [];

        foreach (DesignElementType::cases() as $type) {
            if ($this->find($type->value) !== null) {
                continue;
            }

            $types[] = ['value' => $type->value, 'label' => $type->label()];
            $seen[$type->value] = true;
        }

        foreach ($this->all() as $widget) {
            if ($team !== null && ! $this->availableForTeam($team, $widget->key())) {
                continue;
            }

            if (isset($seen[$widget->key()])) {
                continue;
            }

            $types[] = ['value' => $widget->key(), 'label' => $widget->label()];
            $seen[$widget->key()] = true;
        }

        return $types;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        // The catalog is static code configuration; memoize per process
        // instead of re-mapping every widget schema on each request.
        return $this->memoizedArray ??= $this->buildArray();
    }

    /**
     * Add team-scoped queue filters to the otherwise static widget catalog.
     *
     * @return list<array<string, mixed>>
     */
    public function toArrayForTeam(?Team $team): array
    {
        $widgets = $this->toArray();

        if ($team === null) {
            return $widgets;
        }

        if (! $this->quota->allowsFeature($team, PlanFeature::QueueManagement)) {
            return array_values(array_filter(
                $widgets,
                fn (array $widget) => ! str_starts_with((string) $widget['key'], 'queue_'),
            ));
        }

        $options = [
            'service_id' => [
                ['value' => '0', 'label' => __('All services')],
                ...QueueService::query()
                    ->forTeam($team)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (QueueService $service) => [
                        'value' => (string) $service->id,
                        'label' => $service->name,
                    ])->all(),
            ],
            'location_id' => [
                ['value' => '0', 'label' => __('All locations')],
                ...Location::query()
                    ->forTeam($team)
                    ->orderBy('path')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Location $location) => [
                        'value' => (string) $location->id,
                        'label' => $location->name,
                    ])->all(),
            ],
            'counter_id' => [
                ['value' => '0', 'label' => __('All counters')],
                ...QueueCounter::query()
                    ->forTeam($team)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (QueueCounter $counter) => [
                        'value' => (string) $counter->id,
                        'label' => $counter->name.' ('.$counter->code.')',
                    ])->all(),
            ],
        ];

        return array_map(function (array $widget) use ($options) {
            if (! str_starts_with((string) $widget['key'], 'queue_')) {
                return $widget;
            }

            $widget['schema'] = array_map(function (array $field) use ($options) {
                if (isset($options[$field['name']])) {
                    $field['options'] = $options[$field['name']];
                }

                return $field;
            }, $widget['schema']);

            return $widget;
        }, $widgets);
    }

    public function availableForTeam(Team $team, string $key): bool
    {
        return ! str_starts_with($this->canonicalKey($key), 'queue_')
            || $this->quota->allowsFeature($team, PlanFeature::QueueManagement);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildArray(): array
    {
        return array_map(function (Widget $widget) {
            return [
                'key' => $widget->key(),
                'label' => $widget->label(),
                'description' => $widget->description(),
                'defaults' => $widget->defaults(),
                'schema' => array_map(
                    fn (WidgetField $field) => $field->toArray(),
                    $widget->schema(),
                ),
            ];
        }, $this->all());
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function normalizeSettings(string $key, array $settings): array
    {
        $widget = $this->find($key);

        if ($widget === null) {
            throw ValidationException::withMessages([
                'widget_key' => __('Unknown widget.'),
            ]);
        }

        $normalized = $widget->defaults();

        foreach ($widget->schema() as $field) {
            if (! array_key_exists($field->name, $settings)) {
                if ($field->required && ($normalized[$field->name] === null || $normalized[$field->name] === '')) {
                    throw ValidationException::withMessages([
                        "widget_settings.{$field->name}" => __('This widget setting is required.'),
                    ]);
                }

                continue;
            }

            $normalized[$field->name] = $this->cast($field, $settings[$field->name]);
        }

        return $normalized;
    }

    protected function cast(WidgetField $field, mixed $value): mixed
    {
        return match ($field->type) {
            'number' => is_numeric($value) ? (float) $value : $field->default,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_scalar($value) || $value === null ? $value : $field->default,
        };
    }
}
