<?php

namespace App\Actions\Player;

use App\Actions\Emergency\ResolveActiveEmergency;
use App\Actions\Schedule\ResolveSchedule;
use App\Actions\Widget\HydrateDocumentWidgets;
use App\Actions\Widget\ResolveWidgetData;
use App\Enums\ChannelType;
use App\Enums\DesignStatus;
use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Models\Channel;
use App\Models\Design;
use App\Models\Media;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Support\PlayerManifestCache;
use App\Support\ScheduleClock;
use App\Widgets\WidgetRegistry;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class BuildPlayerManifest
{
    public function __construct(
        protected ResolveSchedule $resolveSchedule,
        protected ResolveActiveEmergency $resolveActiveEmergency,
        protected ResolveWidgetData $resolveWidgetData,
        protected HydrateDocumentWidgets $hydrateWidgets,
        protected WidgetRegistry $widgetRegistry,
    ) {}

    /**
     * Build an immutable playback manifest for a paired screen.
     *
     * Live requests (no explicit timestamp) are served from a short-lived,
     * versioned cache so manifest polls and asset validation stay cheap.
     *
     * @return array<string, mixed>
     */
    public function handle(Screen $screen, ?DateTimeInterface $at = null): array
    {
        if ($at !== null) {
            return $this->build($screen, $at);
        }

        return PlayerManifestCache::remember($screen, fn () => $this->build($screen, now()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function build(Screen $screen, DateTimeInterface $at): array
    {
        $resolved = $this->resolveSchedule->handle($screen, $at);
        $assets = [];
        $channel = $resolved->channelId !== null
            ? Channel::query()->with(['playlist.items.media', 'playlist.items.design', 'playlist.items.template', 'zones.playlist.items.media', 'zones.playlist.items.design', 'zones.playlist.items.template'])->find($resolved->channelId)
            : null;
        $playlist = $resolved->playlistId !== null && ($channel === null || $channel->type !== ChannelType::Playlist)
            ? Playlist::query()->with(['items.media', 'items.design', 'items.template'])->find($resolved->playlistId)
            : $channel?->playlist;

        $zones = [];
        $live = null;
        $type = 'empty';

        if ($channel !== null && $channel->type === ChannelType::Live) {
            $type = 'live';
            $live = [
                'protocol' => $channel->live_protocol?->value,
                'url' => $channel->live_url,
            ];
        } elseif ($channel !== null && $channel->type === ChannelType::Advanced) {
            $type = 'advanced';

            foreach ($channel->zones as $zone) {
                $zonePlaylist = $zone->playlist !== null
                    ? $this->playlistPayload($zone->playlist, $assets, $screen, $at)
                    : null;

                $zones[] = [
                    'name' => $zone->name,
                    'x' => $zone->x,
                    'y' => $zone->y,
                    'width' => $zone->width,
                    'height' => $zone->height,
                    'z_index' => $zone->z_index,
                    'playlist' => $zonePlaylist,
                ];
            }
        } elseif ($playlist !== null) {
            $type = 'playlist';
        }

        $playlistPayload = $playlist !== null && $type === 'playlist'
            ? $this->playlistPayload($playlist, $assets, $screen, $at)
            : null;

        if ($type === 'live' && ! $this->isPlayableLiveUrl($live['url'] ?? null)) {
            $type = 'empty';
        }

        if ($type === 'playlist' && ($playlistPayload === null || ($playlistPayload['items'] ?? []) === [])) {
            $type = 'empty';
        }

        $body = [
            'screen' => [
                'id' => $screen->id,
                'name' => $screen->name,
                'orientation' => $screen->orientation->value,
                'width' => $screen->resolution_width ?? 1920,
                'height' => $screen->resolution_height ?? 1080,
                'timezone' => ScheduleClock::timezoneFor($screen),
            ],
            'playback' => [
                'source' => $resolved->source,
                'schedule_id' => $resolved->scheduleId,
                'schedule_name' => $resolved->scheduleName,
                'type' => $type,
                'channel' => $channel === null ? null : [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'type' => $channel->type->value,
                    'status' => $channel->status->value,
                    'width' => $channel->width,
                    'height' => $channel->height,
                ],
                'live' => $live,
                'zones' => $zones,
                'playlist' => $playlistPayload,
            ],
            'fallback' => $this->fallbackPayload($screen),
            'heartbeat_seconds' => (int) config('signage.player.heartbeat_seconds'),
            'poll_seconds' => (int) config('signage.player.manifest_poll_seconds'),
        ];

        $emergency = $this->resolveActiveEmergency->handle($screen, $at);
        $emergencyPayload = null;

        if ($emergency !== null) {
            $imageKey = $emergency->image !== null ? $this->registerMedia($emergency->image, $assets) : null;
            $videoKey = $emergency->video !== null ? $this->registerMedia($emergency->video, $assets) : null;
            $emergencyPayload = [
                'id' => $emergency->id,
                'severity' => $emergency->severity->value,
                'title' => $emergency->title,
                'message' => $emergency->message,
                'instructions' => $emergency->instructions,
                'background' => $emergency->overlayBackground(),
                'image_asset_key' => $imageKey,
                'video_asset_key' => $videoKey,
                'starts_at' => $emergency->starts_at?->toIso8601String(),
                'expires_at' => $emergency->expires_at?->toIso8601String(),
            ];
            $body['playback']['source'] = $emergency->severity->value;
        }

        $body['emergency'] = $emergencyPayload;

        $assetList = array_values($assets);
        $canonical = json_encode([
            'playback' => $body['playback'],
            'fallback' => $body['fallback'],
            'emergency' => $emergencyPayload,
            'assets' => array_map(fn (array $asset) => [
                'key' => $asset['key'],
                'checksum' => $asset['checksum'],
            ], $assetList),
        ]);

        $version = abs(crc32(is_string($canonical) ? $canonical : ''));

        return [
            'version' => $version,
            'generated_at' => CarbonImmutable::parse($at)->toIso8601String(),
            ...$body,
            'assets' => $assetList,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    protected function playlistPayload(Playlist $playlist, array &$assets, Screen $screen, DateTimeInterface $at): array
    {
        $items = [];

        if ($playlist->status !== PlaylistStatus::Published) {
            return [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'loop' => $playlist->loop,
                'version' => $playlist->version,
                'items' => [],
            ];
        }

        foreach ($playlist->items as $item) {
            if (! $item->enabled) {
                continue;
            }

            if ($item->available_from !== null && $item->available_from->greaterThan($at)) {
                continue;
            }

            if ($item->available_until !== null && $item->available_until->lessThan($at)) {
                continue;
            }

            if ($item->type === PlaylistItemType::Design && ($item->design === null || $item->design->status !== DesignStatus::Published)) {
                continue;
            }

            $items[] = $this->itemPayload($item, $assets, $screen, $at);
        }

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'loop' => $playlist->loop,
            'version' => $playlist->version,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    protected function itemPayload(PlaylistItem $item, array &$assets, Screen $screen, DateTimeInterface $at): array
    {
        $assetKey = null;
        $document = null;
        $url = $item->url;
        $widget = null;

        if ($item->type === PlaylistItemType::Media && $item->media !== null) {
            $assetKey = $this->registerMedia($item->media, $assets);
            $url = $item->media->external_url ?? $url;
        }

        if ($item->type === PlaylistItemType::Design && $item->design !== null) {
            $document = $this->hydrateDocumentWidgets(
                $item->design->normalizedDocument(),
                $screen,
                $at,
            );
            $this->registerDocumentMedia($document, $assets, $item->design->team_id);
            $assetKey = $this->registerDesign($item->design, $document, $assets);
        }

        if ($item->type === PlaylistItemType::Template && $item->template !== null) {
            $document = $this->hydrateDocumentWidgets(
                $item->template->normalizedDocument(),
                $screen,
                $at,
            );
            $this->registerDocumentMedia($document, $assets, $item->playlist->team_id);
        }

        if ($item->type === PlaylistItemType::Widget && filled($item->widget_key)) {
            $widget = $this->resolveWidgetData->handle(
                $screen->team,
                $this->widgetRegistry->canonicalKey((string) $item->widget_key),
                $item->widget_settings ?? [],
                ScheduleClock::timezoneFor($screen),
                $at,
            );
        }

        return [
            'id' => $item->id,
            'key' => 'item:'.$item->id,
            'type' => $item->type->value,
            'title' => $item->title,
            'duration_seconds' => $item->duration_seconds,
            'transition' => $item->transition->value,
            'transition_ms' => $item->transition_ms,
            'asset_key' => $assetKey,
            'document' => $document,
            'url' => $url,
            'widget_key' => $item->widget_key,
            'widget' => $widget,
        ];
    }

    /**
     * Branded idle frame when nothing is playable. Does not change schedule resolution.
     *
     * @return array<string, mixed>
     */
    protected function fallbackPayload(Screen $screen): array
    {
        $screen->loadMissing('team');
        $settings = is_array($screen->team->settings) ? $screen->team->settings : [];
        $metadata = is_array($screen->metadata) ? $screen->metadata : [];
        $image = $metadata['fallback_image'] ?? $settings['fallback_image'] ?? null;

        return [
            'brand' => 'DigSignage',
            'screen_name' => $screen->name,
            'message' => 'Waiting for content',
            'background' => '#0b0b0f',
            'image_url' => is_string($image) && $image !== '' ? $image : null,
        ];
    }

    protected function isPlayableLiveUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function hydrateDocumentWidgets(array $document, Screen $screen, DateTimeInterface $at): array
    {
        return $this->hydrateWidgets->handle(
            $screen->team,
            $document,
            ScheduleClock::timezoneFor($screen),
            $at,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $assets
     */
    protected function registerMedia(Media $media, array &$assets): string
    {
        $key = 'media:'.$media->id;

        if (isset($assets[$key])) {
            return $key;
        }

        $downloadable = filled($media->storage_path) && $media->type->storesFile();

        $assets[$key] = [
            'key' => $key,
            'kind' => 'media',
            'id' => $media->id,
            'name' => $media->name,
            'mime' => $media->mime_type,
            'bytes' => $media->file_size,
            'checksum' => $downloadable ? $media->checksum : null,
            'type' => $media->type->value,
            'url' => $downloadable ? '/api/player/v1/assets/media/'.$media->id : $media->external_url,
        ];

        return $key;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, array<string, mixed>>  $assets
     */
    protected function registerDesign(Design $design, array $document, array &$assets): string
    {
        $key = 'design:'.$design->id;
        $encoded = json_encode($document);

        // Designs (and the widgets they contain) are delivered on the playlist
        // item. Hashing live widget payloads or caching the JSON like a media
        // file blocks playback with checksum mismatches.
        $assets[$key] = [
            'key' => $key,
            'kind' => 'design',
            'id' => $design->id,
            'name' => $design->name,
            'mime' => 'application/json',
            'bytes' => is_string($encoded) ? strlen($encoded) : null,
            'checksum' => null,
            'type' => 'design',
            'url' => null,
        ];

        return $key;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, array<string, mixed>>  $assets
     */
    protected function registerDocumentMedia(array $document, array &$assets, int $teamId): void
    {
        $ids = [];

        foreach ($document['elements'] ?? [] as $element) {
            if (! is_array($element)) {
                continue;
            }

            $props = is_array($element['props'] ?? null) ? $element['props'] : [];
            $mediaId = (int) ($props['media_id'] ?? 0);

            if ($mediaId > 0) {
                $ids[] = $mediaId;
            }
        }

        if ($ids === []) {
            return;
        }

        $mediaItems = Media::query()->where('team_id', $teamId)->whereKey(array_values(array_unique($ids)))->get();

        foreach ($mediaItems as $media) {
            $this->registerMedia($media, $assets);
        }
    }
}
