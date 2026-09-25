<?php

namespace App\Services\Deploy;

use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use App\Models\AppRelease;
use App\Support\AppVersion;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Installs and rolls back releases using the zero-downtime layout:
 *
 *     {base}/releases/{version}-{timestamp}   one folder per release
 *     {base}/shared/.env, {base}/shared/storage
 *     {base}/current -> releases/...          what the web server serves
 *
 * The new release is unpacked and prepared (dependencies, assets, migrations,
 * caches) beside the live one, then `current` is swapped atomically, so
 * visitors never hit a half-installed app.
 */
class ReleaseInstaller
{
    public const LOCK = 'app-releases:deploy';

    public function __construct(
        protected ReleaseArchive $archive,
        protected GitHubReleases $github,
    ) {}

    /**
     * Whether this server can install releases, and why not when it cannot.
     *
     * @return array{enabled: bool, reason: string|null, base_path: string|null, current_path: string|null}
     */
    public function status(): array
    {
        $base = $this->basePath();
        $current = $base !== null && is_link($base.'/current') ? realpath($base.'/current') : false;
        $problem = match (true) {
            ! config('deploy.enabled') => ['The in-app updater is off. Set DEPLOY_UPDATER_ENABLED=true on a server that uses the release layout.', []],
            $base === null => ['Set DEPLOY_BASE_PATH to the folder that holds releases/, shared/ and current.', []],
            ! is_dir($base.'/releases') || ! is_dir($base.'/shared') => [':path is missing its releases/ or shared/ folder. See docs/deployment.md.', ['path' => $base]],
            default => null,
        };
        $reason = null;

        if ($problem !== null) {
            $translated = __($problem[0], $problem[1]);
            $reason = is_string($translated) ? $translated : $problem[0];
        }

        return [
            'enabled' => $reason === null,
            'reason' => $reason,
            'base_path' => $base,
            'current_path' => $current === false ? null : $current,
        ];
    }

    public function install(AppRelease $release): void
    {
        $lock = $this->acquire($release, AppReleaseStatus::Failed);

        if ($lock === null) {
            return;
        }

        $directory = null;

        try {
            $this->assertEnabled();
            $this->begin($release, __('Installing :version', ['version' => $release->version]));

            $package = $this->package($release);
            $directory = $this->basePath().'/releases/'.$package->version.'-'.now()->format('YmdHis');

            $release->appendLog("Unpacking into {$directory}\n");
            $this->archive->extract($this->packageFile($release), $package, $directory);

            if (! $package->hasVendor || ! $package->hasBuild) {
                $release->appendLog("The package has no prebuilt dependencies or assets; building them on the server.\n");
            }

            $script = is_file($directory.'/scripts/deploy/release.sh')
                ? $directory.'/scripts/deploy/release.sh'
                : base_path('scripts/deploy/release.sh');

            $this->runScript($release, $script, [$directory]);

            DB::transaction(function () use ($release, $directory): void {
                AppRelease::query()
                    ->whereKeyNot($release->id)
                    ->where('status', AppReleaseStatus::Active)
                    ->update(['status' => AppReleaseStatus::Inactive]);

                $release->forceFill([
                    'status' => AppReleaseStatus::Active,
                    'release_path' => $directory,
                    'activated_at' => now(),
                    'finished_at' => now(),
                    'error' => null,
                ])->save();
            });

            $release->appendLog(__('Version :version is live.', ['version' => $release->version])."\n");
            $this->deletePackage($release);
            $this->forgetPrunedReleases();
        } catch (Throwable $exception) {
            $this->fail($release, $exception);

            if ($directory !== null && is_dir($directory) && ! $this->isCurrent($directory)) {
                File::deleteDirectory($directory);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Point `current` back at an earlier release that is still on disk.
     */
    public function rollback(AppRelease $release): void
    {
        $lock = $this->acquire($release, AppReleaseStatus::Inactive);

        if ($lock === null) {
            return;
        }

        try {
            $this->assertEnabled();

            if (! $this->canRollBackTo($release)) {
                throw new ReleaseException(__('Version :version is no longer on the server.', ['version' => $release->version]));
            }

            $this->begin($release, __('Rolling back to :version', ['version' => $release->version]));
            $this->runScript($release, base_path('scripts/deploy/rollback.sh'), [(string) $release->release_path]);

            DB::transaction(function () use ($release): void {
                AppRelease::query()
                    ->whereKeyNot($release->id)
                    ->where('status', AppReleaseStatus::Active)
                    ->update(['status' => AppReleaseStatus::Inactive]);

                $release->forceFill([
                    'status' => AppReleaseStatus::Active,
                    'activated_at' => now(),
                    'finished_at' => now(),
                    'error' => null,
                ])->save();
            });

            $release->appendLog(__('Version :version is live again. Database changes from newer versions were kept.', ['version' => $release->version])."\n");
        } catch (Throwable $exception) {
            $this->fail($release, $exception, AppReleaseStatus::Inactive);
        } finally {
            $lock->release();
        }
    }

    public function canRollBackTo(AppRelease $release): bool
    {
        return $release->status === AppReleaseStatus::Inactive
            && filled($release->release_path)
            && is_dir((string) $release->release_path)
            && $this->isInsideReleases((string) $release->release_path);
    }

    public function basePath(): ?string
    {
        $base = config('deploy.base_path');

        return is_string($base) && $base !== '' ? rtrim($base, '/\\') : null;
    }

    /**
     * Resolve the package for a release, downloading it from GitHub first
     * when needed, and fill in version details from its contents.
     */
    protected function package(AppRelease $release): ReleasePackage
    {
        if ($release->source === AppReleaseSource::GitHub && blank($release->package_path)) {
            [$repository, $ref, $asset] = $this->githubSource($release);
            $path = 'releases/packages/'.$release->id.'-'.bin2hex(random_bytes(6)).'.zip';

            $release->appendLog(__('Downloading :ref from :repository', ['ref' => $ref, 'repository' => $repository])."\n");
            $this->github->download($repository, $ref, $asset, Storage::disk('local')->path($path));
            $release->forceFill(['package_path' => $path])->save();
        }

        $package = $this->archive->inspect($this->packageFile($release));

        if ($release->checksum !== null && ! hash_equals($release->checksum, $package->checksum)) {
            throw new ReleaseException(__('The package checksum does not match. It may have been changed after upload.'));
        }

        if (AppVersion::normalize($release->version) === null) {
            // A branch or bare tag: the package's VERSION file decides.
            if (version_compare($package->version, AppVersion::current()) < 0) {
                throw new ReleaseException(__('The package is version :package, older than the live version :current. Use rollback instead.', ['package' => $package->version, 'current' => AppVersion::current()]));
            }

            $release->forceFill(['version' => $package->version])->save();
        } elseif ($release->version !== $package->version) {
            throw new ReleaseException(__('The package is version :package, not :expected.', ['package' => $package->version, 'expected' => $release->version]));
        }

        $release->forceFill([
            'checksum' => $package->checksum,
            'package_size' => $package->size,
            'title' => $release->title ?? $package->title,
            'notes' => $release->notes ?? $package->notes,
            'features' => filled($release->features) ? $release->features : $package->features,
        ])->save();

        return $package;
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    protected function githubSource(AppRelease $release): array
    {
        // source_ref is "owner/repo@ref", optionally followed by "#asset-url".
        [$location, $asset] = array_pad(explode('#', (string) $release->source_ref, 2), 2, null);
        [$repository, $ref] = array_pad(explode('@', (string) $location, 2), 2, null);

        if (blank($repository) || blank($ref)) {
            throw new ReleaseException(__('This release has no GitHub source.'));
        }

        return [(string) $repository, (string) $ref, filled($asset) ? (string) $asset : null];
    }

    /**
     * @param  list<string>  $arguments
     */
    protected function runScript(AppRelease $release, string $script, array $arguments): void
    {
        if (! is_file($script)) {
            throw new ReleaseException(__('Deploy script :script is missing.', ['script' => $script]));
        }

        $release->appendLog('$ '.basename($script).' '.implode(' ', $arguments)."\n");
        $streamed = false;

        $result = Process::path((string) $this->basePath())
            ->timeout(max(60, (int) config('deploy.timeout')))
            ->env([
                'DEPLOY_BASE_PATH' => (string) $this->basePath(),
                'DEPLOY_KEEP_RELEASES' => (string) max(2, (int) config('deploy.keep_releases')),
                'DEPLOY_PHP' => (string) config('deploy.binaries.php'),
                'DEPLOY_COMPOSER' => (string) config('deploy.binaries.composer'),
                'DEPLOY_NPM' => (string) config('deploy.binaries.npm'),
                'DEPLOY_RELOAD_COMMAND' => (string) config('deploy.reload_command'),
            ])
            ->run([(string) config('deploy.binaries.bash'), $script, ...$arguments], function (string $type, string $output) use ($release, &$streamed): void {
                $streamed = true;
                $release->appendLog($output);
            });

        if (! $streamed) {
            // Output that was not streamed arrives in one piece at the end.
            $release->appendLog($result->output().$result->errorOutput());
        }

        if ($result->failed()) {
            throw new ReleaseException(__('The deploy script stopped with exit code :code. The live version was not changed.', ['code' => (string) $result->exitCode()]));
        }
    }

    protected function begin(AppRelease $release, string $heading): void
    {
        $release->forceFill([
            'status' => AppReleaseStatus::Installing,
            'started_at' => now(),
            'finished_at' => null,
            'error' => null,
        ])->save();

        $release->appendLog("\n== {$heading} (".now()->toDateTimeString().") ==\n");
    }

    protected function fail(AppRelease $release, Throwable $exception, AppReleaseStatus $status = AppReleaseStatus::Failed): void
    {
        $message = $exception instanceof ReleaseException
            ? $exception->getMessage()
            : __('Unexpected error: :message', ['message' => $exception->getMessage()]);

        if (! $exception instanceof ReleaseException) {
            report($exception);
        }

        $release->appendLog("ERROR: {$message}\n");
        $release->forceFill([
            'status' => $status,
            'error' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
        ])->save();
    }

    protected function assertEnabled(): void
    {
        $status = $this->status();

        if (! $status['enabled']) {
            throw new ReleaseException((string) $status['reason']);
        }
    }

    /**
     * Take the deploy lock, recording a failure on the release when another
     * install or rollback already holds it.
     */
    protected function acquire(AppRelease $release, AppReleaseStatus $onBusy): ?Lock
    {
        $lock = Cache::lock(self::LOCK, max(60, (int) config('deploy.timeout')) + 120);

        if ($lock->get()) {
            return $lock;
        }

        $this->fail($release, new ReleaseException(__('Another install or rollback is already running.')), $onBusy);

        return null;
    }

    protected function packageFile(AppRelease $release): string
    {
        if (blank($release->package_path)) {
            throw new ReleaseException(__('This release has no package to install.'));
        }

        return Storage::disk('local')->path((string) $release->package_path);
    }

    protected function deletePackage(AppRelease $release): void
    {
        if (filled($release->package_path)) {
            Storage::disk('local')->delete((string) $release->package_path);
            $release->forceFill(['package_path' => null])->save();
        }
    }

    /**
     * The deploy script prunes old release folders; forget their paths so
     * they are no longer offered for rollback.
     */
    protected function forgetPrunedReleases(): void
    {
        AppRelease::query()
            ->whereNotNull('release_path')
            ->where('status', '!=', AppReleaseStatus::Active)
            ->get()
            ->reject(fn (AppRelease $release) => is_dir((string) $release->release_path))
            ->each(fn (AppRelease $release) => $release->forceFill(['release_path' => null])->save());
    }

    protected function isCurrent(string $directory): bool
    {
        $current = $this->status()['current_path'];

        return $current !== null && realpath($directory) === $current;
    }

    protected function isInsideReleases(string $directory): bool
    {
        $releases = realpath($this->basePath().'/releases');
        $target = realpath($directory);

        return $releases !== false && $target !== false && str_starts_with($target, $releases.DIRECTORY_SEPARATOR);
    }
}
