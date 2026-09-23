<?php

namespace App\Support;

use App\Enums\ApiScope;
use App\Enums\WebhookEvent;

class PartnerApiDocumentation
{
    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $paths = [];

        foreach (self::resources() as $path => $resource) {
            $paths[$path] = [
                'get' => self::operation('List '.$resource['name'], $resource['read'], true),
            ];

            if ($resource['write']) {
                $paths[$path]['post'] = self::operation('Create '.$resource['name'], $resource['write'], false, 201);
            }

            $item = $path.'/{id}';
            $paths[$item] = [
                'get' => self::operation('Show '.$resource['name'], $resource['read']),
            ];

            if ($resource['write']) {
                $paths[$item]['patch'] = self::operation('Update '.$resource['name'], $resource['write']);
                $paths[$item]['delete'] = self::operation('Delete '.$resource['name'], $resource['write'], false, 204);
            }
        }

        $paths['/api/v1'] = [
            'get' => self::operation('API root', null),
        ];
        $paths['/api/v1/analytics'] = [
            'get' => self::operation('Analytics summary', ApiScope::AnalyticsRead->value),
        ];
        $paths['/api/v1/proof-of-play'] = [
            'get' => self::operation('Proof of play', ApiScope::ProofOfPlayRead->value, true),
        ];
        $paths['/api/v1/screen-groups/{id}/screens'] = [
            'put' => self::operation('Replace screen group membership', ApiScope::ScreenGroupsWrite->value),
        ];
        $paths['/api/v1/queue/services'] = [
            'get' => self::operation('List queue services', ApiScope::QueueRead->value, true),
        ];
        $paths['/api/v1/queue/tickets'] = [
            'post' => self::operation('Issue a queue ticket', ApiScope::QueueWrite->value, false, 201),
        ];
        $paths['/api/v1/queue/tickets']['post']['parameters'] = [[
            'name' => 'Idempotency-Key',
            'in' => 'header',
            'required' => false,
            'description' => 'Reuse the same value when retrying ticket creation.',
            'schema' => ['type' => 'string', 'maxLength' => 255],
        ]];
        $paths['/api/v1/queue/tickets']['post']['responses']['200'] = [
            'description' => 'Existing ticket returned for an idempotent replay',
        ];
        $paths['/api/v1/queue/tickets/{id}'] = [
            'get' => self::operation('Show a queue ticket and journey', ApiScope::QueueRead->value),
        ];
        $paths['/api/v1/queue/tickets/{id}/cancel'] = [
            'post' => self::operation('Cancel a waiting queue ticket', ApiScope::QueueWrite->value),
        ];
        $paths['/api/v1/counters/{id}/next'] = [
            'post' => self::operation('Call the next eligible ticket', ApiScope::QueueWrite->value),
        ];
        $paths['/api/v1/tickets/{id}/recall'] = [
            'post' => self::operation('Recall the active ticket', ApiScope::QueueWrite->value),
        ];
        $paths['/api/v1/tickets/{id}/complete'] = [
            'post' => self::operation('Complete the active ticket', ApiScope::QueueWrite->value),
        ];
        $paths['/api/v1/tickets/{id}/transfer'] = [
            'post' => self::operation('Transfer the active ticket', ApiScope::QueueWrite->value),
        ];
        $paths['/api/v1/queue/status'] = [
            'get' => self::operation('Current queue operations status', ApiScope::QueueRead->value),
        ];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'DigSignage Partner API',
                'version' => '1.0.0',
                'description' => 'Versioned REST API for screens, content, schedules, queue management, analytics, and proof of play. Authenticate with a bearer token from Settings → API. Webhook signatures use HMAC SHA-256 of the raw JSON body (`X-DigSignage-Signature: sha256=<hex>`).',
            ],
            'servers' => [
                ['url' => url('/')],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'paths' => $paths,
            'x-scopes' => ApiScope::options(),
            'x-webhook-events' => WebhookEvent::options(),
        ];
    }

    /**
     * @return array<string, array{name: string, read: string, write: string|null}>
     */
    protected static function resources(): array
    {
        return [
            '/api/v1/screens' => ['name' => 'screens', 'read' => ApiScope::ScreensRead->value, 'write' => ApiScope::ScreensWrite->value],
            '/api/v1/screen-groups' => ['name' => 'screen groups', 'read' => ApiScope::ScreenGroupsRead->value, 'write' => ApiScope::ScreenGroupsWrite->value],
            '/api/v1/locations' => ['name' => 'locations', 'read' => ApiScope::LocationsRead->value, 'write' => ApiScope::LocationsWrite->value],
            '/api/v1/media' => ['name' => 'media', 'read' => ApiScope::MediaRead->value, 'write' => ApiScope::MediaWrite->value],
            '/api/v1/templates' => ['name' => 'templates', 'read' => ApiScope::TemplatesRead->value, 'write' => null],
            '/api/v1/playlists' => ['name' => 'playlists', 'read' => ApiScope::PlaylistsRead->value, 'write' => ApiScope::PlaylistsWrite->value],
            '/api/v1/channels' => ['name' => 'channels', 'read' => ApiScope::ChannelsRead->value, 'write' => ApiScope::ChannelsWrite->value],
            '/api/v1/schedules' => ['name' => 'schedules', 'read' => ApiScope::SchedulesRead->value, 'write' => ApiScope::SchedulesWrite->value],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function operation(string $summary, ?string $scope, bool $paginated = false, int $status = 200): array
    {
        $operation = [
            'summary' => $summary,
            'responses' => [
                (string) $status => ['description' => 'OK'],
                '401' => ['description' => 'Unauthenticated'],
                '403' => ['description' => 'Forbidden'],
            ],
        ];

        if ($scope !== null) {
            $operation['description'] = 'Required scope: `'.$scope.'`. Write scopes also allow the matching read scope.';
        }

        if ($paginated) {
            $operation['parameters'] = [
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]],
            ];
        }

        return $operation;
    }
}
