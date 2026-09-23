<?php

namespace App\Support;

use App\Models\Playlist;
use App\Models\Team;
use Illuminate\Validation\ValidationException;

final class ChannelZones
{
    /**
     * Default three-zone layout for advanced channels.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultLayout(): array
    {
        return [
            ['name' => 'Main', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 70, 'z_index' => 1, 'playlist_id' => null],
            ['name' => 'Ticker', 'x' => 0, 'y' => 70, 'width' => 70, 'height' => 30, 'z_index' => 2, 'playlist_id' => null],
            ['name' => 'Sidebar', 'x' => 70, 'y' => 70, 'width' => 30, 'height' => 30, 'z_index' => 3, 'playlist_id' => null],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $zones
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $zones, Team $team): array
    {
        $normalized = [];
        $index = 0;

        foreach ($zones as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $playlistId = self::nullableId($zone['playlist_id'] ?? null);

            if ($playlistId !== null && Playlist::query()->forTeam($team)->whereKey($playlistId)->doesntExist()) {
                throw ValidationException::withMessages([
                    "zones.{$index}.playlist_id" => __('The selected playlist is not available to this team.'),
                ]);
            }

            $name = is_string($zone['name'] ?? null) ? trim($zone['name']) : '';
            $width = max(1, min(100, (int) ($zone['width'] ?? 100)));
            $height = max(1, min(100, (int) ($zone['height'] ?? 100)));
            $x = max(0, min(100 - $width, (int) ($zone['x'] ?? 0)));
            $y = max(0, min(100 - $height, (int) ($zone['y'] ?? 0)));

            $normalized[] = [
                'name' => $name !== '' ? $name : 'Zone '.($index + 1),
                'playlist_id' => $playlistId,
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
                'z_index' => max(0, (int) ($zone['z_index'] ?? ($index + 1))),
                'position' => $index + 1,
            ];
            $index++;
        }

        return $normalized;
    }

    protected static function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
