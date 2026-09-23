<?php

namespace App\Services\Media;

use App\Actions\Notifications\DispatchSignageAlert;
use App\Actions\Partner\DispatchPartnerWebhook;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaType;
use App\Enums\SignageAlert;
use App\Enums\WebhookEvent;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessMediaFile
{
    /**
     * Absolute path of the working copy for the item being processed.
     */
    protected ?string $sourcePath = null;

    /**
     * Temporary files to remove once the item has been processed.
     *
     * @var list<string>
     */
    protected array $temporaryFiles = [];

    public function __construct(protected DispatchSignageAlert $alerts) {}

    /**
     * Extract metadata and generate derivatives for an uploaded file.
     */
    public function handle(Media $media): void
    {
        $media->update([
            'processing_status' => MediaProcessingStatus::Processing,
            'processing_error' => null,
        ]);

        try {
            if ($media->storage_path) {
                $absolutePath = $this->sourcePath($media);

                if ($absolutePath === null || ! is_file($absolutePath)) {
                    throw new \RuntimeException('Source file is missing.');
                }

                $checksum = hash_file('sha256', $absolutePath);

                if (is_string($checksum)) {
                    $media->checksum = $checksum;
                }
                $media->file_size = filesize($absolutePath) ?: $media->file_size;
            }

            match ($media->type) {
                MediaType::Image => $this->processImage($media),
                MediaType::Video => $this->processVideo($media),
                MediaType::Audio => $this->processAudio($media),
                MediaType::Pdf, MediaType::HtmlPackage => null,
                default => null,
            };

            $media->processing_status = MediaProcessingStatus::Ready;
            $media->save();

            $media->loadMissing('team');
            app(DispatchPartnerWebhook::class)->handle(
                $media->team,
                WebhookEvent::MediaProcessed,
                ['id' => $media->id, 'name' => $media->name, 'type' => $media->type->value],
            );
        } catch (\Throwable $exception) {
            $media->forceFill([
                'processing_status' => MediaProcessingStatus::Failed,
                'processing_error' => $exception->getMessage(),
            ])->save();

            $media->loadMissing('team');
            $this->alerts->queue(
                $media->team,
                SignageAlert::MediaProcessingFailed,
                __('Media processing failed: :name', ['name' => $media->name]),
                $exception->getMessage(),
                ['media_id' => $media->id],
                'media_failed:'.$media->id,
                86400,
            );
        } finally {
            $this->cleanUp();
        }
    }

    /**
     * Absolute path to a readable copy of the original file.
     *
     * GD and FFmpeg need a real path. Object-storage backends have none, so
     * the object is streamed to a temporary file once per item and removed
     * when processing finishes.
     */
    protected function sourcePath(Media $media): ?string
    {
        if ($this->sourcePath !== null) {
            return $this->sourcePath;
        }

        if (! $media->storage_path) {
            return null;
        }

        $disk = Storage::disk($media->disk());

        if ($this->isLocal($media)) {
            return $this->sourcePath = $disk->path($media->storage_path);
        }

        if (! $disk->exists($media->storage_path)) {
            return null;
        }

        $extension = pathinfo((string) $media->storage_path, PATHINFO_EXTENSION);
        $temporary = tempnam(sys_get_temp_dir(), 'digsignage-media-');

        if ($temporary === false) {
            throw new \RuntimeException('Unable to create a temporary working file.');
        }

        if ($extension !== '') {
            $withExtension = $temporary.'.'.$extension;
            @rename($temporary, $withExtension);
            $temporary = $withExtension;
        }

        $this->temporaryFiles[] = $temporary;

        $stream = $disk->readStream($media->storage_path);

        if ($stream === null) {
            throw new \RuntimeException('Source file could not be read from storage.');
        }

        $target = fopen($temporary, 'wb');

        if ($target === false) {
            throw new \RuntimeException('Unable to open a temporary working file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $this->sourcePath = $temporary;
    }

    /**
     * Whether the item lives on a local-driver disk.
     */
    protected function isLocal(Media $media): bool
    {
        return (string) config('filesystems.disks.'.$media->disk().'.driver') === 'local';
    }

    /**
     * Discard temporary working files and reset per-item state.
     */
    protected function cleanUp(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->temporaryFiles = [];
        $this->sourcePath = null;
    }

    /**
     * Detect image dimensions and write a JPEG thumbnail when possible.
     */
    protected function processImage(Media $media): void
    {
        $absolutePath = $this->sourcePath($media);

        if ($absolutePath === null) {
            return;
        }

        $info = @getimagesize($absolutePath);

        if (is_array($info)) {
            $media->width = $info[0];
            $media->height = $info[1];
        }

        if (($media->mime_type === 'image/svg+xml') || ! function_exists('imagecreatetruecolor')) {
            return;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxWidth = (int) config('media.thumbnail_width');
        $maxHeight = (int) config('media.thumbnail_height');
        $ratio = min($maxWidth / max($sourceWidth, 1), $maxHeight / max($sourceHeight, 1), 1);
        $thumbWidth = max((int) round($sourceWidth * $ratio), 1);
        $thumbHeight = max((int) round($sourceHeight * $ratio), 1);
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($thumbnail, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if (! is_string($jpeg) || $jpeg === '') {
            return;
        }

        $thumbPath = $media->team_id.'/thumbnails/'.Str::uuid().'.jpg';
        Storage::disk($media->disk())->put($thumbPath, $jpeg);
        $media->thumbnail_path = $thumbPath;
    }

    /**
     * Probe video metadata with FFmpeg when available.
     */
    protected function processVideo(Media $media): void
    {
        $probe = $this->ffprobe($media);

        if ($probe === null) {
            $media->metadata = array_merge($media->metadata ?? [], [
                'ffmpeg' => 'unavailable',
            ]);

            return;
        }

        $media->duration = isset($probe['format']['duration'])
            ? (int) round((float) $probe['format']['duration'])
            : $media->duration;

        $streams = [];

        if (isset($probe['streams']) && is_array($probe['streams'])) {
            foreach ($probe['streams'] as $stream) {
                if (is_array($stream)) {
                    $streams[] = $stream;
                }
            }
        }

        $videoStream = collect($streams)->first(
            fn (array $stream): bool => ($stream['codec_type'] ?? null) === 'video',
        );

        if (is_array($videoStream)) {
            $media->width = isset($videoStream['width']) ? (int) $videoStream['width'] : $media->width;
            $media->height = isset($videoStream['height']) ? (int) $videoStream['height'] : $media->height;
            $media->metadata = array_merge($media->metadata ?? [], [
                'codec' => $videoStream['codec_name'] ?? null,
            ]);
        }

        $this->generateVideoThumbnail($media);
    }

    /**
     * Probe audio duration with FFmpeg when available.
     */
    protected function processAudio(Media $media): void
    {
        $probe = $this->ffprobe($media);

        if ($probe === null) {
            return;
        }

        $media->duration = isset($probe['format']['duration'])
            ? (int) round((float) $probe['format']['duration'])
            : $media->duration;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ffprobe(Media $media): ?array
    {
        $path = $this->sourcePath($media);

        if ($path === null) {
            return null;
        }

        $process = new Process([
            (string) config('media.ffprobe_path'),
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $path,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $decoded = json_decode($process->getOutput(), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Capture a video thumbnail with FFmpeg when available.
     */
    protected function generateVideoThumbnail(Media $media): void
    {
        $source = $this->sourcePath($media);

        if ($source === null) {
            return;
        }

        $thumbPath = $media->team_id.'/thumbnails/'.Str::uuid().'.jpg';
        $local = $this->isLocal($media);

        if ($local) {
            Storage::disk($media->disk())->makeDirectory($media->team_id.'/thumbnails');
            $destination = Storage::disk($media->disk())->path($thumbPath);
        } else {
            $destination = tempnam(sys_get_temp_dir(), 'digsignage-thumb-');

            if ($destination === false) {
                return;
            }

            $destination .= '.jpg';
            $this->temporaryFiles[] = $destination;
        }

        $process = new Process([
            (string) config('media.ffmpeg_path'),
            '-y',
            '-i', $source,
            '-ss', '00:00:01',
            '-vframes', '1',
            $destination,
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return;
        }

        if ($local) {
            if (Storage::disk($media->disk())->exists($thumbPath)) {
                $media->thumbnail_path = $thumbPath;
            }

            return;
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            return;
        }

        $contents = file_get_contents($destination);

        if ($contents === false) {
            return;
        }

        Storage::disk($media->disk())->put($thumbPath, $contents);
        $media->thumbnail_path = $thumbPath;
    }
}
