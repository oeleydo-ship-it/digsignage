<?php

namespace App\Http\Controllers\Player;

use App\Actions\Player\BuildPlayerManifest;
use App\Enums\DesignStatus;
use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\Media;
use App\Models\Screen;
use App\Support\TeamQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerAssetController extends Controller
{
    /**
     * Stream a media file that belongs to the player's team, or redirect to
     * a signed object-storage URL when the media disk is S3-based.
     */
    public function media(Request $request, int $media, BuildPlayerManifest $buildManifest): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $screen = $this->screen($request);
        $asset = Media::query()->forTeam($screen->team)->whereKey($media)->firstOrFail();

        abort_unless($this->manifestContains($buildManifest->handle($screen), 'media:'.$asset->id), 403);
        abort_unless(filled($asset->storage_path), 404);
        abort_unless(Storage::disk($asset->disk())->exists($asset->storage_path), 404);

        app(TeamQuota::class)->recordBandwidth($screen->team, (int) $asset->file_size);

        return $asset->streamOriginal();
    }

    /**
     * Return a design document JSON payload.
     */
    public function design(Request $request, int $design, BuildPlayerManifest $buildManifest): JsonResponse
    {
        $screen = $this->screen($request);
        $record = Design::query()->forTeam($screen->team)->whereKey($design)->firstOrFail();

        abort_unless($record->status === DesignStatus::Published, 403);
        abort_unless($this->manifestContains($buildManifest->handle($screen), 'design:'.$record->id), 403);

        return response()->json($record->normalizedDocument());
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function manifestContains(array $manifest, string $key): bool
    {
        foreach ($manifest['assets'] ?? [] as $asset) {
            if (is_array($asset) && ($asset['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    protected function screen(Request $request): Screen
    {
        $screen = $request->attributes->get('playerScreen');

        abort_unless($screen instanceof Screen, 401);

        return $screen;
    }
}
