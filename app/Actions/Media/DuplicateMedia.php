<?php

namespace App\Actions\Media;

use App\Enums\MediaProcessingStatus;
use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use App\Support\TeamQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateMedia
{
    /**
     * Duplicate a media record and its stored files.
     */
    public function handle(Media $media): Media
    {
        return DB::transaction(function () use ($media) {
            app(TeamQuota::class)->assertCanStoreBytes($media->team, (int) $media->file_size);
            $copy = $media->replicate([
                'checksum',
                'storage_path',
                'thumbnail_path',
                'processing_error',
                'archived_at',
            ]);
            $copy->name = $media->name.' copy';
            $copy->usage_count = 0;
            $copy->processing_status = $media->type->storesFile()
                ? MediaProcessingStatus::Pending
                : MediaProcessingStatus::Ready;

            if ($media->storage_path && Storage::disk($media->disk())->exists($media->storage_path)) {
                $extension = pathinfo($media->storage_path, PATHINFO_EXTENSION);
                $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
                $path = $media->team_id.'/originals/'.$filename;
                Storage::disk($media->disk())->copy($media->storage_path, $path);
                $copy->filename = $filename;
                $copy->storage_path = $path;
            }

            $copy->thumbnail_path = null;
            $copy->save();
            $copy->tags()->sync($media->tags()->pluck('media_tags.id')->all());

            if ($copy->type->storesFile() && $copy->storage_path) {
                ProcessMediaJob::dispatch($copy);
            }

            return $copy;
        });
    }
}
