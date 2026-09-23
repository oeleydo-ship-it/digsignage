<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaSource;
use App\Jobs\ProcessMediaJob;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Team;
use App\Models\User;
use App\Support\HtmlPackageInspector;
use App\Support\MediaClassifier;
use App\Support\StorageDisks;
use App\Support\TeamQuota;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadMediaFiles
{
    /**
     * Store uploaded files and queue processing.
     *
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $tags
     * @return list<Media>
     */
    public function handle(User $user, Team $team, array $files, ?int $folderId, array $tags = []): array
    {
        $folder = $this->folder($team, $folderId);
        $incoming = 0;

        foreach ($files as $file) {
            $incoming += (int) $file->getSize();
        }

        app(TeamQuota::class)->assertCanStoreBytes($team, $incoming);

        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->storeOne($user, $team, $file, $folder, $tags);
        }

        return $stored;
    }

    /**
     * @param  list<string>  $tags
     */
    protected function storeOne(User $user, Team $team, UploadedFile $file, ?MediaFolder $folder, array $tags): Media
    {
        $type = MediaClassifier::fromUpload($file);

        if ($type === null) {
            throw ValidationException::withMessages([
                'files' => __('The file type is not allowed.'),
            ]);
        }

        $maxKilobytes = MediaClassifier::maxKilobytes($type);

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw ValidationException::withMessages([
                'files' => __('The file is larger than the allowed size.'),
            ]);
        }

        if ($type->value === 'html_package') {
            HtmlPackageInspector::assertSafe((string) $file->getRealPath());
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $path = $team->id.'/originals/'.$filename;
        $mime = MediaClassifier::detectedMime($file);

        $disk = app(StorageDisks::class)->forTeam($team);

        $written = Storage::disk($disk)->putFileAs($team->id.'/originals', $file, $filename);
        if ($written === false) {
            throw ValidationException::withMessages(['files' => __('The media file could not be stored. Check available storage and retry.')]);
        }

        return DB::transaction(function () use ($user, $team, $file, $folder, $tags, $type, $filename, $path, $mime, $disk) {
            $media = Media::query()->create([
                'team_id' => $team->id,
                'folder_id' => $folder?->id,
                'uploaded_by' => $user->id,
                'type' => $type,
                'source' => MediaSource::File,
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $filename,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => $file->getSize(),
                'storage_path' => $path,
                'storage_disk' => $disk,
                'processing_status' => MediaProcessingStatus::Pending,
                'metadata' => [],
            ]);

            app(SyncMediaTags::class)->handle($team, $media, $tags);

            ProcessMediaJob::dispatch($media)->afterCommit();

            app(RecordOrganizationAudit::class)->handle(
                $team,
                AuditAction::MediaUploaded,
                $user,
                'media',
                $media->id,
                null,
                ['name' => $media->name, 'filename' => $media->original_filename],
            );

            return $media;
        });
    }

    /**
     * Resolve a folder in the current team.
     */
    protected function folder(Team $team, ?int $folderId): ?MediaFolder
    {
        if ($folderId === null) {
            return null;
        }

        return MediaFolder::query()->forTeam($team)->whereKey($folderId)->firstOrFail();
    }
}
