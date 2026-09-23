<?php

namespace App\Actions\Playlist;

use App\Enums\PlaylistItemType;
use App\Enums\PlaylistStatus;
use App\Models\Playlist;
use App\Models\User;

class DuplicatePlaylist
{
    /**
     * Duplicate a playlist and its items as a new draft.
     */
    public function handle(User $user, Playlist $playlist): Playlist
    {
        $playlist->loadMissing('items');

        $legacyTemplateIds = [];

        foreach ($playlist->items as $item) {
            if ($item->type === PlaylistItemType::Template && $item->template_id) {
                $legacyTemplateIds[] = (int) $item->template_id;
            }
        }

        return app(SavePlaylist::class)->handle($user, $playlist->team, [
            'name' => $playlist->name.' copy',
            'description' => $playlist->description,
            'status' => PlaylistStatus::Draft->value,
            'loop' => $playlist->loop,
            'items' => $playlist->itemsSnapshot(),
        ], null, $legacyTemplateIds);
    }
}
