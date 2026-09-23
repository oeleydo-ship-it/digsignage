<?php

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateMedia
{
    /**
     * Update media metadata, folder, tags, and archive state.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, Media $media, array $attributes): Media
    {
        return DB::transaction(function () use ($team, $media, $attributes) {
            $folderId = $attributes['folder_id'] ?? $media->folder_id;

            if ($folderId !== null && $folderId !== '') {
                $folder = MediaFolder::query()->forTeam($team)->whereKey($folderId)->first();

                if (! $folder) {
                    throw ValidationException::withMessages([
                        'folder_id' => __('The selected folder is invalid.'),
                    ]);
                }

                $media->folder_id = $folder->id;
            } elseif (array_key_exists('folder_id', $attributes) && $attributes['folder_id'] === null) {
                $media->folder_id = null;
            }

            if (isset($attributes['name'])) {
                $media->name = $attributes['name'];
            }

            if (array_key_exists('archived', $attributes)) {
                $media->archived_at = $attributes['archived']
                    ? Carbon::parse((string) now())
                    : null;
            }

            $media->save();

            if (array_key_exists('tags', $attributes)) {
                app(SyncMediaTags::class)->handle($team, $media, $attributes['tags'] ?? []);
            }

            return $media->refresh();
        });
    }
}
