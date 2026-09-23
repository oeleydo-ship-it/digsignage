<?php

namespace App\Actions\Media;

use App\Models\MediaFolder;
use Illuminate\Validation\ValidationException;

class DeleteMediaFolder
{
    /**
     * Delete a folder that has no children or media.
     */
    public function handle(MediaFolder $folder): void
    {
        if ($folder->children()->exists() || $folder->media()->exists()) {
            throw ValidationException::withMessages([
                'folder' => __('Move or delete files and subfolders before deleting this folder.'),
            ]);
        }

        $folder->delete();
    }
}
