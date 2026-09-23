<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\MediaTag;
use App\Models\Team;

class SyncMediaTags
{
    /**
     * Sync tag names onto a media item for the current team.
     *
     * @param  list<string>  $names
     */
    public function handle(Team $team, Media $media, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(function (string $name) use ($team) {
                return MediaTag::query()->firstOrCreate(
                    ['team_id' => $team->id, 'name' => $name],
                )->id;
            })
            ->all();

        $media->tags()->sync($ids);
    }
}
