<?php

namespace App\Services\Deploy;

use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use App\Models\AppRelease;
use App\Support\AppVersion;
use App\Support\ReleaseNotes;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the release history in step with the code that is actually running.
 */
class ReleaseRegistry
{
    /**
     * Record the running version when it was deployed outside the updater
     * (first run, manual deploy), and expire installs that stopped
     * reporting.
     */
    public function sync(): ?AppRelease
    {
        $this->expireStale();

        if (AppRelease::query()->whereIn('status', [AppReleaseStatus::Queued, AppReleaseStatus::Installing])->exists()) {
            // Mid-install the new code may already be live; let the installer finish.
            return $this->active();
        }

        $active = $this->active();
        $current = AppVersion::current();

        if ($active?->version === $current) {
            return $active;
        }

        return $this->record($current, AppReleaseSource::Existing, base_path());
    }

    /**
     * Mark a version as live, e.g. after the GitHub Actions deploy.
     */
    public function record(string $version, AppReleaseSource $source, ?string $path = null, ?string $ref = null, ?string $changelog = null): AppRelease
    {
        $changelog ??= $this->changelog($path);
        $notes = $changelog !== null
            ? ReleaseNotes::fromChangelog($changelog, $version)
            : ['title' => null, 'notes' => null, 'features' => []];

        return DB::transaction(function () use ($version, $source, $path, $ref, $notes): AppRelease {
            AppRelease::query()
                ->where('status', AppReleaseStatus::Active)
                ->update(['status' => AppReleaseStatus::Inactive]);

            return AppRelease::query()->create([
                'version' => $version,
                'title' => $notes['title'],
                'notes' => $notes['notes'],
                'features' => $notes['features'],
                'source' => $source,
                'source_ref' => $ref,
                'release_path' => $path,
                'status' => AppReleaseStatus::Active,
                'activated_at' => now(),
                'finished_at' => now(),
            ]);
        });
    }

    public function active(): ?AppRelease
    {
        return AppRelease::query()
            ->where('status', AppReleaseStatus::Active)
            ->latest('activated_at')
            ->latest('id')
            ->first();
    }

    public function busy(): bool
    {
        return AppRelease::query()
            ->whereIn('status', [AppReleaseStatus::Queued, AppReleaseStatus::Installing])
            ->exists();
    }

    protected function expireStale(): void
    {
        $limit = max(60, (int) config('deploy.timeout')) + 300;

        AppRelease::query()
            ->whereIn('status', [AppReleaseStatus::Queued, AppReleaseStatus::Installing])
            ->where('started_at', '<', now()->subSeconds($limit))
            ->get()
            ->each(function (AppRelease $release): void {
                $release->appendLog("ERROR: The installer stopped responding.\n");
                $release->forceFill([
                    // A stalled rollback leaves an older, still usable release.
                    'status' => filled($release->release_path) ? AppReleaseStatus::Inactive : AppReleaseStatus::Failed,
                    'error' => __('The installer stopped responding. Check storage/logs/releases.log on the server.'),
                    'finished_at' => now(),
                ])->save();
            });
    }

    private function changelog(?string $path): ?string
    {
        $file = ($path ?? base_path()).'/CHANGELOG.md';

        return is_file($file) ? (string) file_get_contents($file) : null;
    }
}
