<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaFileController extends Controller
{
    /**
     * Stream the original file for authorized team members, or redirect to
     * a signed object-storage URL when the media disk is S3-based.
     */
    public function show(Request $request, string $current_team, Media $media): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $media);
        abort_unless($media->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);
        abort_unless(filled($media->storage_path), 404);
        abort_unless(Storage::disk($media->disk())->exists($media->storage_path), 404);

        return $media->streamOriginal();
    }

    /**
     * Stream the generated thumbnail when present.
     */
    public function thumbnail(Request $request, string $current_team, Media $media): StreamedResponse
    {
        Gate::authorize('view', $media);
        abort_unless($media->team_id === $request->user()->currentTeam->id, 403);
        abort_unless($current_team === $request->user()->currentTeam->slug, 403);
        abort_unless(filled($media->thumbnail_path), 404);
        abort_unless(Storage::disk($media->disk())->exists($media->thumbnail_path), 404);

        return Storage::disk($media->disk())->response(
            $media->thumbnail_path,
            'thumbnail.jpg',
            [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline',
            ],
        );
    }
}
