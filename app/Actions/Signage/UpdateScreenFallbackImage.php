<?php

namespace App\Actions\Signage;

use App\Models\Screen;
use App\Support\FallbackImage;
use Illuminate\Http\UploadedFile;

class UpdateScreenFallbackImage
{
    /**
     * Set or clear a per-screen fallback image override.
     *
     * Persisted to metadata.fallback_image, which BuildPlayerManifest reads
     * ahead of the team-level settings.fallback_image default.
     */
    public function handle(Screen $screen, ?UploadedFile $image): void
    {
        $metadata = is_array($screen->metadata) ? $screen->metadata : [];
        $previous = $metadata['fallback_image'] ?? null;

        if ($image === null) {
            unset($metadata['fallback_image']);
        } else {
            $metadata['fallback_image'] = FallbackImage::store($screen->team_id, $image, 'screen-'.$screen->id);
        }

        $screen->forceFill(['metadata' => $metadata])->save();

        if ($previous !== ($metadata['fallback_image'] ?? null)) {
            FallbackImage::delete($previous);
        }
    }
}
