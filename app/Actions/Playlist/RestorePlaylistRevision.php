<?php

namespace App\Actions\Playlist;

use App\Models\Playlist;
use App\Models\PlaylistRevision;
use App\Models\User;

class RestorePlaylistRevision
{
    /**
     * Restore a previous playlist sequence onto the current playlist.
     */
    public function handle(User $user, Playlist $playlist, PlaylistRevision $revision): Playlist
    {
        abort_unless($revision->playlist_id === $playlist->id, 404);
        abort_unless($revision->team_id === $playlist->team_id, 403);

        $legacyTemplateIds = [];

        foreach ($revision->items ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'template' && isset($item['template_id'])) {
                $legacyTemplateIds[] = (int) $item['template_id'];
            }
        }

        return app(SavePlaylist::class)->handle($user, $playlist->team, [
            'name' => $playlist->name,
            'description' => $playlist->description,
            'status' => $playlist->status->value,
            'loop' => $revision->loop,
            'items' => $revision->items,
        ], $playlist, $legacyTemplateIds);
    }
}
