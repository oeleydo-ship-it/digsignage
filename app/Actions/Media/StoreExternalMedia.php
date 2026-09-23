<?php

namespace App\Actions\Media;

use App\Actions\Audit\RecordOrganizationAudit;
use App\Enums\AuditAction;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Team;
use App\Models\User;
use App\Support\MediaClassifier;
use Illuminate\Support\Facades\DB;

class StoreExternalMedia
{
    /**
     * Store an external URL or live stream.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $tags
     */
    public function handle(User $user, Team $team, array $attributes, array $tags = []): Media
    {
        $folder = null;

        if (! empty($attributes['folder_id'])) {
            $folder = MediaFolder::query()
                ->forTeam($team)
                ->whereKey($attributes['folder_id'])
                ->firstOrFail();
        }

        $type = MediaType::from($attributes['type']);
        $url = (string) $attributes['external_url'];

        if ($type === MediaType::Url) {
            $inferred = MediaClassifier::fromUrl($url);

            if ($inferred === MediaType::Video) {
                $type = MediaType::Video;
            }
        }

        return DB::transaction(function () use ($user, $team, $attributes, $tags, $folder, $type, $url) {
            $media = Media::query()->create([
                'team_id' => $team->id,
                'folder_id' => $folder?->id,
                'uploaded_by' => $user->id,
                'type' => $type,
                'source' => MediaSource::External,
                'name' => $attributes['name'],
                'external_url' => $url,
                'mime_type' => $type === MediaType::Video ? 'video/mp4' : null,
                'processing_status' => MediaProcessingStatus::Ready,
                'metadata' => [],
            ]);

            app(SyncMediaTags::class)->handle($team, $media, $tags);

            app(RecordOrganizationAudit::class)->handle(
                $team,
                AuditAction::MediaUploaded,
                $user,
                'media',
                $media->id,
                null,
                ['name' => $media->name, 'external_url' => $media->external_url],
            );

            return $media;
        });
    }
}
