<?php

namespace App\Actions\Media;

use App\Models\Media;

class DeleteMedia
{
    /**
     * Permanently delete a media record and its stored files.
     */
    public function handle(Media $media): void
    {
        $media->deleteStoredFiles();
        $media->tags()->detach();
        $media->forceDelete();
    }
}
