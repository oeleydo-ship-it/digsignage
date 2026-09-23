<?php

namespace App\Enums;

enum ApiScope: string
{
    case ScreensRead = 'screens:read';
    case ScreensWrite = 'screens:write';
    case ScreenGroupsRead = 'screen-groups:read';
    case ScreenGroupsWrite = 'screen-groups:write';
    case LocationsRead = 'locations:read';
    case LocationsWrite = 'locations:write';
    case MediaRead = 'media:read';
    case MediaWrite = 'media:write';
    case TemplatesRead = 'templates:read';
    case TemplatesWrite = 'templates:write';
    case PlaylistsRead = 'playlists:read';
    case PlaylistsWrite = 'playlists:write';
    case ChannelsRead = 'channels:read';
    case ChannelsWrite = 'channels:write';
    case SchedulesRead = 'schedules:read';
    case SchedulesWrite = 'schedules:write';
    case AnalyticsRead = 'analytics:read';
    case ProofOfPlayRead = 'proof-of-play:read';
    case QueueRead = 'queue:read';
    case QueueWrite = 'queue:write';

    public function label(): string
    {
        return match ($this) {
            self::ScreensRead => 'Read screens',
            self::ScreensWrite => 'Write screens',
            self::ScreenGroupsRead => 'Read screen groups',
            self::ScreenGroupsWrite => 'Write screen groups',
            self::LocationsRead => 'Read locations',
            self::LocationsWrite => 'Write locations',
            self::MediaRead => 'Read media',
            self::MediaWrite => 'Write media',
            self::TemplatesRead => 'Read templates',
            self::TemplatesWrite => 'Write templates',
            self::PlaylistsRead => 'Read playlists',
            self::PlaylistsWrite => 'Write playlists',
            self::ChannelsRead => 'Read channels',
            self::ChannelsWrite => 'Write channels',
            self::SchedulesRead => 'Read schedules',
            self::SchedulesWrite => 'Write schedules',
            self::AnalyticsRead => 'Read analytics',
            self::ProofOfPlayRead => 'Read proof of play',
            self::QueueRead => 'Read queue management',
            self::QueueWrite => 'Operate queue management',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $scope) => ['value' => $scope->value, 'label' => $scope->label()],
            self::cases(),
        );
    }
}
