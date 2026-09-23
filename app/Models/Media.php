<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Support\StorageDisks;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $folder_id
 * @property int|null $uploaded_by
 * @property MediaType $type
 * @property MediaSource $source
 * @property string $name
 * @property string|null $filename
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int|null $duration
 * @property int|null $width
 * @property int|null $height
 * @property string|null $checksum
 * @property string|null $storage_path
 * @property string|null $thumbnail_path
 * @property string|null $storage_disk
 * @property string|null $external_url
 * @property MediaProcessingStatus $processing_status
 * @property string|null $processing_error
 * @property int $usage_count
 * @property Carbon|null $archived_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read MediaFolder|null $folder
 * @property-read User|null $uploader
 * @property-read Collection<int, MediaTag> $tags
 *
 * @method static Builder<static> notArchived()
 */
#[Fillable([
    'team_id',
    'folder_id',
    'uploaded_by',
    'type',
    'source',
    'name',
    'filename',
    'original_filename',
    'mime_type',
    'file_size',
    'duration',
    'width',
    'height',
    'checksum',
    'storage_path',
    'thumbnail_path',
    'storage_disk',
    'external_url',
    'processing_status',
    'processing_error',
    'usage_count',
    'archived_at',
    'metadata',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use BelongsToTeam, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsToMany<MediaTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_media_tag')->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Determine if the item is archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Storage disk used for original files and thumbnails.
     *
     * Files stay on the backend they were written to, so moving an
     * organization to a new backend does not orphan its existing library.
     */
    public function disk(): string
    {
        return app(StorageDisks::class)->resolveStored($this->storage_disk);
    }

    /**
     * Public preview image for library and playlist cards.
     */
    public function previewUrl(string $teamSlug): ?string
    {
        if (filled($this->thumbnail_path)) {
            return route('media.thumbnail', [
                'current_team' => $teamSlug,
                'media' => $this,
            ]);
        }

        if ($this->type === MediaType::Image && filled($this->storage_path)) {
            return route('media.file', [
                'current_team' => $teamSlug,
                'media' => $this,
            ]);
        }

        return null;
    }

    /**
     * Stream the original file inline.
     *
     * Local disks use a BinaryFileResponse with HTTP range support so video
     * players can seek. S3-compatible disks redirect to a short-lived signed
     * URL so large media is served by object storage / a CDN edge instead of
     * being piped through PHP.
     */
    public function streamOriginal(): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $disk = Storage::disk($this->disk());
        $filename = $this->original_filename ?: $this->filename ?: 'media';
        $safeName = str_replace(['"', "\r", "\n", '\\'], '', basename((string) $filename)) ?: 'media';
        $mime = $this->mime_type ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $isSvg = str_contains(strtolower($mime), 'svg')
            || str_ends_with(strtolower($safeName), '.svg');

        if ($isSvg) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'";
        }

        $driver = (string) config('filesystems.disks.'.$this->disk().'.driver');

        if ($driver === 'local') {
            return response()->file($disk->path((string) $this->storage_path), $headers);
        }

        if ($driver === 's3' && ! $isSvg) {
            $minutes = max(1, (int) config('media.temporary_url_minutes', 10));

            return redirect()->away($disk->temporaryUrl(
                (string) $this->storage_path,
                now()->addMinutes($minutes),
                ['ResponseContentType' => $mime, 'ResponseContentDisposition' => 'inline; filename="'.$safeName.'"'],
            ));
        }

        return $disk->response((string) $this->storage_path, $safeName, $headers);
    }

    public function deleteStoredFiles(): void
    {
        $disk = Storage::disk($this->disk());

        if (filled($this->storage_path)) {
            $disk->delete($this->storage_path);
        }

        if (filled($this->thumbnail_path)) {
            $disk->delete($this->thumbnail_path);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'source' => MediaSource::class,
            'processing_status' => MediaProcessingStatus::class,
            'archived_at' => 'datetime',
            'metadata' => 'array',
            'file_size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'usage_count' => 'integer',
        ];
    }
}
