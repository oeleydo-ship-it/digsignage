<?php

namespace App\Support;

use App\Models\Team;
use App\Widgets\WidgetRegistry;

final class ContentApps
{
    /**
     * First-party content apps that hydrate widgets from team settings.integrations.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     widgets: list<string>,
     *     fields: list<array<string, mixed>>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'weather',
                'label' => 'Weather',
                'description' => 'Default city and units for weather widgets.',
                'widgets' => ['weather'],
                'fields' => [
                    ['name' => 'location', 'type' => 'string', 'label' => 'Default location', 'help' => 'Used when a weather widget leaves Location empty.', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'units', 'type' => 'select', 'label' => 'Units', 'options' => [
                        ['value' => 'celsius', 'label' => 'Celsius'],
                        ['value' => 'fahrenheit', 'label' => 'Fahrenheit'],
                    ], 'rules' => ['nullable', 'string', 'in:celsius,fahrenheit']],
                ],
            ],
            [
                'key' => 'rss',
                'label' => 'RSS',
                'description' => 'Default RSS or Atom feed for RSS widgets.',
                'widgets' => ['rss'],
                'fields' => [
                    ['name' => 'feed_url', 'type' => 'url', 'label' => 'Feed URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'limit', 'type' => 'number', 'label' => 'Item limit', 'rules' => ['nullable', 'integer', 'min:1', 'max:12']],
                ],
            ],
            [
                'key' => 'news',
                'label' => 'News',
                'description' => 'Default headlines feed for news widgets.',
                'widgets' => ['news'],
                'fields' => [
                    ['name' => 'feed_url', 'type' => 'url', 'label' => 'News feed URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'limit', 'type' => 'number', 'label' => 'Item limit', 'rules' => ['nullable', 'integer', 'min:1', 'max:12']],
                ],
            ],
            [
                'key' => 'youtube',
                'label' => 'YouTube',
                'description' => 'Default muted looping video for YouTube widgets.',
                'widgets' => ['youtube'],
                'fields' => [
                    ['name' => 'url', 'type' => 'url', 'label' => 'Video URL', 'rules' => ['nullable', 'string', 'max:2048']],
                ],
            ],
            [
                'key' => 'web_page',
                'label' => 'Web page',
                'description' => 'Default embeddable page for web page widgets.',
                'widgets' => ['web_page'],
                'fields' => [
                    ['name' => 'url', 'type' => 'url', 'label' => 'Page URL', 'rules' => ['nullable', 'string', 'max:2048']],
                ],
            ],
            [
                'key' => 'json_api',
                'label' => 'JSON / API',
                'description' => 'HTTPS JSON endpoint, optional bearer token, and field paths.',
                'widgets' => ['json_api'],
                'fields' => [
                    ['name' => 'url', 'type' => 'url', 'label' => 'Endpoint URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'title_path', 'type' => 'string', 'label' => 'Title path', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'body_path', 'type' => 'string', 'label' => 'Body path', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'token', 'type' => 'password', 'label' => 'API token', 'help' => 'Stored on the organization. Sent as a Bearer header. Never shown to players.', 'rules' => ['nullable', 'string', 'max:500']],
                ],
            ],
            [
                'key' => 'qr_code',
                'label' => 'QR code',
                'description' => 'Default destination encoded by QR widgets.',
                'widgets' => ['qr_code'],
                'fields' => [
                    ['name' => 'value', 'type' => 'string', 'label' => 'QR value', 'rules' => ['nullable', 'string', 'max:500']],
                ],
            ],
            [
                'key' => 'calendar',
                'label' => 'Calendar',
                'description' => 'ICS feed or a list of events for calendar widgets.',
                'widgets' => ['calendar'],
                'fields' => [
                    ['name' => 'ics_url', 'type' => 'url', 'label' => 'ICS / calendar URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'events', 'type' => 'textarea', 'label' => 'Events (time | title)', 'help' => 'Used when the ICS URL is empty.', 'rules' => ['nullable', 'string', 'max:8000']],
                ],
            ],
            [
                'key' => 'booking',
                'label' => 'Bookings',
                'description' => 'JSON bookings endpoint or a daily board for booking widgets.',
                'widgets' => ['booking'],
                'fields' => [
                    ['name' => 'endpoint_url', 'type' => 'url', 'label' => 'Bookings JSON URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'resource', 'type' => 'string', 'label' => 'Default resource', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'heading', 'type' => 'string', 'label' => 'Default heading', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'bookings', 'type' => 'textarea', 'label' => 'Bookings (start-end | title | status)', 'rules' => ['nullable', 'string', 'max:8000']],
                ],
            ],
            [
                'key' => 'social_wall',
                'label' => 'Social wall',
                'description' => 'Curated posts or a JSON source for social wall widgets.',
                'widgets' => ['social_wall'],
                'fields' => [
                    ['name' => 'source_url', 'type' => 'url', 'label' => 'Posts JSON URL', 'rules' => ['nullable', 'string', 'max:2048']],
                    ['name' => 'heading', 'type' => 'string', 'label' => 'Default heading', 'rules' => ['nullable', 'string', 'max:120']],
                    ['name' => 'posts', 'type' => 'textarea', 'label' => 'Posts (@handle | message)', 'rules' => ['nullable', 'string', 'max:8000']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $rules = [
            'apps' => ['required', 'array'],
        ];

        foreach (self::definitions() as $app) {
            $key = $app['key'];
            $rules["apps.{$key}"] = ['sometimes', 'array'];
            $rules["apps.{$key}.enabled"] = ['sometimes', 'boolean'];

            foreach ($app['fields'] as $field) {
                $rules["apps.{$key}.{$field['name']}"] = $field['rules'];
            }
        }

        return $rules;
    }

    /**
     * Sanitized payload for the Apps settings page (secrets never leave the server).
     *
     * @return list<array<string, mixed>>
     */
    public static function forPage(Team $team): array
    {
        $stored = self::stored($team);
        $apps = [];

        foreach (self::definitions() as $definition) {
            $key = $definition['key'];
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $values = [];

            foreach ($definition['fields'] as $field) {
                $name = $field['name'];

                if ($name === 'token') {
                    $values['has_token'] = filled($row['token'] ?? null);
                    $values['token'] = '';

                    continue;
                }

                $fallback = $field['type'] === 'number' ? null : '';

                if ($field['type'] === 'select' && is_array($field['options'][0] ?? null)) {
                    $fallback = (string) ($field['options'][0]['value'] ?? '');
                }

                $values[$name] = $row[$name] ?? $fallback;
            }

            $enabled = (bool) ($row['enabled'] ?? false);

            $apps[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'widgets' => $definition['widgets'],
                'fields' => array_map(function (array $field) {
                    unset($field['rules']);

                    return $field;
                }, $definition['fields']),
                'enabled' => $enabled,
                'connected' => $enabled && self::isConnected($key, $row),
                'values' => $values,
            ];
        }

        return $apps;
    }

    /**
     * @return array<string, mixed>
     */
    public static function stored(Team $team): array
    {
        $settings = is_array($team->settings) ? $team->settings : [];
        $integrations = $settings['integrations'] ?? [];

        return is_array($integrations) ? $integrations : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(Team $team, string $key): array
    {
        $row = self::stored($team)[$key] ?? [];

        if (! is_array($row) || ! ($row['enabled'] ?? false)) {
            return [];
        }

        return $row;
    }

    /**
     * Fill blank widget props from the team's enabled content app.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mergeWidgetSettings(Team $team, string $key, array $settings): array
    {
        $canonical = app(WidgetRegistry::class)->canonicalKey($key);
        $config = self::config($team, $canonical);

        if ($config === []) {
            return $settings;
        }

        $map = self::widgetFieldMap()[$canonical] ?? [];

        foreach ($map as $widgetField => $appField) {
            if (! self::isBlank($settings[$widgetField] ?? null) || self::isBlank($config[$appField] ?? null)) {
                continue;
            }

            $settings[$widgetField] = $config[$appField];
        }

        return $settings;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function widgetFieldMap(): array
    {
        return [
            'weather' => ['location' => 'location', 'units' => 'units'],
            'rss' => ['feed_url' => 'feed_url', 'limit' => 'limit'],
            'news' => ['feed_url' => 'feed_url', 'limit' => 'limit'],
            'youtube' => ['url' => 'url'],
            'web_page' => ['url' => 'url'],
            'json_api' => ['url' => 'url', 'title_path' => 'title_path', 'body_path' => 'body_path'],
            'qr_code' => ['value' => 'value'],
            'calendar' => ['events' => 'events'],
            'booking' => ['bookings' => 'bookings', 'resource' => 'resource', 'heading' => 'heading'],
            'social_wall' => ['posts' => 'posts', 'heading' => 'heading'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function isConnected(string $key, array $row): bool
    {
        return match ($key) {
            'weather' => filled($row['location'] ?? null),
            'rss', 'news' => filled($row['feed_url'] ?? null),
            'youtube', 'web_page', 'json_api' => filled($row['url'] ?? null),
            'qr_code' => filled($row['value'] ?? null),
            'calendar' => filled($row['ics_url'] ?? null) || filled($row['events'] ?? null),
            'booking' => filled($row['endpoint_url'] ?? null) || filled($row['bookings'] ?? null),
            'social_wall' => filled($row['source_url'] ?? null) || filled($row['posts'] ?? null),
            default => false,
        };
    }

    public static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /**
     * Authorization header for the JSON app token, if configured.
     *
     * @return array<string, string>
     */
    public static function jsonApiHeaders(Team $team): array
    {
        $token = trim((string) (self::config($team, 'json_api')['token'] ?? ''));

        if ($token === '') {
            return [];
        }

        if (str_contains($token, ' ')) {
            return ['Authorization' => $token];
        }

        return ['Authorization' => 'Bearer '.$token];
    }
}
