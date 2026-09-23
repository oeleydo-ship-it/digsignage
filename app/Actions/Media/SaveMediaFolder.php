<?php

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMediaFolder
{
    /**
     * Create or update a media folder.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes, ?MediaFolder $folder = null): MediaFolder
    {
        return DB::transaction(function () use ($team, $attributes, $folder) {
            $parent = $this->parent($team, $attributes['parent_id'] ?? null);
            $folder ??= new MediaFolder(['team_id' => $team->id]);

            if ($parent && $folder->exists && ($parent->is($folder) || $folder->isAncestorOf($parent))) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A folder cannot be moved under itself or one of its descendants.'),
                ]);
            }

            $oldPrefix = $folder->exists ? $folder->descendantPathPrefix() : null;

            $folder->fill([
                'parent_id' => $parent?->id,
                'name' => $attributes['name'],
            ]);
            $folder->rebuildPath();
            $folder->save();

            if ($oldPrefix !== null && $oldPrefix !== $folder->descendantPathPrefix()) {
                $newPrefix = $folder->descendantPathPrefix();

                MediaFolder::query()
                    ->where('team_id', $folder->team_id)
                    ->where('path', 'like', $oldPrefix.'%')
                    ->orderBy('depth')
                    ->get()
                    ->each(function (MediaFolder $descendant) use ($oldPrefix, $newPrefix) {
                        $descendant->path = $newPrefix.substr($descendant->path, strlen($oldPrefix));
                        $descendant->depth = max(substr_count($descendant->path, '/') - 1, 0);
                        $descendant->save();
                    });
            }

            return $folder->refresh();
        });
    }

    /**
     * Resolve a parent folder in the same team.
     */
    protected function parent(Team $team, mixed $parentId): ?MediaFolder
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return MediaFolder::query()->forTeam($team)->whereKey($parentId)->firstOrFail();
    }
}
