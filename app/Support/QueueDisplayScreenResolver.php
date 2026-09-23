<?php

namespace App\Support;

use App\Actions\Player\BuildPlayerManifest;
use App\Models\QueueService;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class QueueDisplayScreenResolver
{
    public function __construct(protected BuildPlayerManifest $buildManifest) {}

    /**
     * Fast broadcast fan-out. Players apply their own active widget filters,
     * so resolving every screen manifest here would delay the call sound.
     *
     * @return Collection<int, Screen>
     */
    public function forTeam(int $teamId): Collection
    {
        return Screen::query()
            ->where('team_id', $teamId)
            ->whereNotNull('device_uuid')
            ->where('device_uuid', '!=', '')
            ->get(['id', 'device_uuid']);
    }

    /**
     * Resolve paired screens whose active manifest contains a queue widget
     * matching the changed service. This includes scheduled and multi-zone
     * playback because the same manifest resolver is used by the player.
     *
     * @return Collection<int, Screen>
     */
    public function forService(int $teamId, int $serviceId): Collection
    {
        $service = QueueService::query()
            ->where('team_id', $teamId)
            ->with('counters:id')
            ->find($serviceId);

        if ($service === null) {
            return new Collection;
        }

        $counterIds = $service->counters->modelKeys();

        return Screen::query()
            ->where('team_id', $teamId)
            ->whereNotNull('device_uuid')
            ->where('device_uuid', '!=', '')
            ->with('team')
            ->get()
            ->filter(function (Screen $screen) use ($serviceId, $service, $counterIds): bool {
                try {
                    return $this->manifestMatches(
                        $this->buildManifest->handle($screen),
                        $serviceId,
                        $service->location_id,
                        $counterIds,
                    );
                } catch (Throwable) {
                    return false;
                }
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<int>  $counterIds
     */
    protected function manifestMatches(array $manifest, int $serviceId, ?int $locationId, array $counterIds): bool
    {
        $playback = is_array($manifest['playback'] ?? null) ? $manifest['playback'] : [];
        $playlists = [];

        if (is_array($playback['playlist'] ?? null)) {
            $playlists[] = $playback['playlist'];
        }

        foreach ($playback['zones'] ?? [] as $zone) {
            if (is_array($zone) && is_array($zone['playlist'] ?? null)) {
                $playlists[] = $zone['playlist'];
            }
        }

        foreach ($playlists as $playlist) {
            foreach ($playlist['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if ($this->widgetMatches($item['widget'] ?? null, $serviceId, $locationId, $counterIds)) {
                    return true;
                }

                $document = is_array($item['document'] ?? null) ? $item['document'] : [];

                foreach ($document['elements'] ?? [] as $element) {
                    if (! is_array($element)) {
                        continue;
                    }

                    $widget = is_array($element['widget'] ?? null)
                        ? $element['widget']
                        : [
                            'key' => $element['type'] ?? null,
                            'settings' => $element['props'] ?? [],
                        ];

                    if ($this->widgetMatches($widget, $serviceId, $locationId, $counterIds)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $counterIds
     */
    protected function widgetMatches(mixed $widget, int $serviceId, ?int $locationId, array $counterIds): bool
    {
        if (! is_array($widget) || ! str_starts_with((string) ($widget['key'] ?? ''), 'queue_')) {
            return false;
        }

        $settings = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];
        $selectedService = (int) ($settings['service_id'] ?? 0);
        $selectedLocation = (int) ($settings['location_id'] ?? 0);
        $selectedCounter = (int) ($settings['counter_id'] ?? 0);

        return ($selectedService === 0 || $selectedService === $serviceId)
            && ($selectedLocation === 0 || $selectedLocation === $locationId)
            && ($selectedCounter === 0 || in_array($selectedCounter, $counterIds, true));
    }
}
