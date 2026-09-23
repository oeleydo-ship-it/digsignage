<?php

namespace App\Actions\Signage;

use App\Models\Team;
use App\Support\FallbackImage;
use Illuminate\Http\UploadedFile;

class UpdateTeamFallbackImage
{
    /**
     * Set or clear the team-wide player fallback screen image.
     *
     * Persisted to settings.fallback_image, which BuildPlayerManifest reads.
     */
    public function handle(Team $team, ?UploadedFile $image): void
    {
        $settings = is_array($team->settings) ? $team->settings : [];
        $previous = $settings['fallback_image'] ?? null;

        if ($image === null) {
            unset($settings['fallback_image']);
        } else {
            $settings['fallback_image'] = FallbackImage::store($team->id, $image);
        }

        $team->forceFill(['settings' => $settings])->save();

        if ($previous !== ($settings['fallback_image'] ?? null)) {
            FallbackImage::delete($previous);
        }
    }
}
