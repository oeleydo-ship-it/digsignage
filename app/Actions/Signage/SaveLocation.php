<?php

namespace App\Actions\Signage;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLocation
{
    /**
     * Create or update a location within a team.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes, ?Location $location = null): Location
    {
        return DB::transaction(function () use ($team, $attributes, $location) {
            $parent = $this->parent($team, $attributes['parent_id'] ?? null);

            $location ??= new Location(['team_id' => $team->id]);

            if ($parent && $location->exists && ($parent->is($location) || $location->isAncestorOf($parent))) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A location cannot be moved under itself or one of its descendants.'),
                ]);
            }

            $oldPrefix = $location->exists ? $location->descendantPathPrefix() : null;

            $location->fill([
                'parent_id' => $parent?->id,
                'type' => $attributes['type'],
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'address' => $attributes['address'] ?? null,
                'timezone' => $attributes['timezone'] ?? null,
                'tags' => $attributes['tags'] ?? [],
                'metadata' => $attributes['metadata'] ?? [],
            ]);

            $location->rebuildPath();
            $location->save();

            if ($oldPrefix !== null && $oldPrefix !== $location->descendantPathPrefix()) {
                $this->rebuildDescendants($location, $oldPrefix);
            }

            return $location->refresh();
        });
    }

    /**
     * Rebuild descendant paths after a move.
     */
    protected function rebuildDescendants(Location $location, string $oldPrefix): void
    {
        $newPrefix = $location->descendantPathPrefix();

        Location::query()
            ->where('team_id', $location->team_id)
            ->where('path', 'like', $oldPrefix.'%')
            ->orderBy('depth')
            ->get()
            ->each(function (Location $descendant) use ($oldPrefix, $newPrefix) {
                $descendant->path = $newPrefix.substr($descendant->path, strlen($oldPrefix));
                $descendant->depth = max(substr_count($descendant->path, '/') - 1, 0);
                $descendant->save();
            });
    }

    /**
     * Resolve a parent location in the same team.
     */
    protected function parent(Team $team, mixed $parentId): ?Location
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return Location::query()
            ->forTeam($team)
            ->whereKey($parentId)
            ->firstOrFail();
    }
}
