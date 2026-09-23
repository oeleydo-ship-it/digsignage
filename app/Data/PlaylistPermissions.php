<?php

namespace App\Data;

readonly class PlaylistPermissions
{
    public function __construct(
        public bool $canViewPlaylists,
        public bool $canCreatePlaylist,
        public bool $canUpdatePlaylist,
        public bool $canDeletePlaylist,
    ) {
        //
    }
}
