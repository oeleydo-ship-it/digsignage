<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ConfirmReleaseActionRequest;
use App\Http\Requests\Platform\InstallGitHubReleaseRequest;
use App\Http\Requests\Platform\UploadReleaseRequest;
use App\Models\AppRelease;
use App\Services\Deploy\GitHubReleases;
use App\Services\Deploy\ReleaseArchive;
use App\Services\Deploy\ReleaseException;
use App\Services\Deploy\ReleaseInstaller;
use App\Services\Deploy\ReleaseRegistry;
use App\Services\Deploy\ReleaseRunner;
use App\Support\AppVersion;
use App\Support\ReleaseNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin → Updates: version history, installing new releases from
 * GitHub or an uploaded package, and rolling back.
 */
class ReleaseController extends Controller
{
    public function __construct(
        protected ReleaseRegistry $registry,
        protected ReleaseInstaller $installer,
        protected ReleaseRunner $runner,
        protected GitHubReleases $github,
        protected RecordPlatformAudit $audit,
    ) {}

    public function index(): Response
    {
        $active = $this->registry->sync();

        return Inertia::render('platform/updates/index', [
            'current' => [
                'version' => AppVersion::current(),
                'release' => $active !== null ? $this->summary($active) : null,
            ],
            'updater' => $this->installer->status(),
            'busy' => $this->registry->busy(),
            'releases' => AppRelease::query()
                ->with('creator:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (AppRelease $release) => $this->summary($release)),
            'github' => [
                'repositories' => array_values((array) config('deploy.github.repositories')),
                'has_token' => filled(config('deploy.github.token')),
            ],
            'maxPackageMb' => (int) config('deploy.max_package_mb'),
            // Loaded on demand by "Check for updates".
            'available' => Inertia::optional(fn () => $this->available()),
        ]);
    }

    public function upload(UploadReleaseRequest $request, ReleaseArchive $archive): RedirectResponse
    {
        $this->assertIdle();

        $path = $request->file('package')->storeAs('releases/packages', Str::uuid().'.zip', 'local');

        try {
            $package = $archive->inspect(Storage::disk('local')->path((string) $path));

            $expected = $request->validated('checksum');

            if (is_string($expected) && $expected !== '' && ! hash_equals(strtolower($expected), $package->checksum)) {
                throw new ReleaseException(__('The file does not match the SHA-256 checksum you entered.'));
            }

            $this->assertInstallable($package->version, allowSame: false);
        } catch (ReleaseException $exception) {
            Storage::disk('local')->delete((string) $path);

            throw ValidationException::withMessages(['package' => $exception->getMessage()]);
        }

        $release = AppRelease::query()->create([
            'version' => $package->version,
            'title' => $package->title,
            'notes' => $package->notes,
            'features' => $package->features,
            'source' => AppReleaseSource::Upload,
            'source_ref' => Str::limit((string) $request->file('package')->getClientOriginalName(), 250, ''),
            'package_path' => $path,
            'checksum' => $package->checksum,
            'package_size' => $package->size,
            'status' => AppReleaseStatus::Ready,
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->handle(PlatformAuditAction::ReleaseUploaded, $request->user(), 'app_release', $release->id, null, [
            'version' => $release->version,
            'checksum' => $release->checksum,
        ]);

        if ($request->boolean('install_now')) {
            return $this->startInstall($release, $request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :version is ready to install.', ['version' => $release->version])]);

        return to_route('platform.updates.index');
    }

    public function installFromGitHub(InstallGitHubReleaseRequest $request): RedirectResponse
    {
        $this->assertIdle();

        try {
            ['repository' => $repository, 'ref' => $ref] = $this->github->parseUrl((string) $request->validated('url'));
            $published = $this->github->release($repository, $ref);

            if ($published === null && $ref === null) {
                throw new ReleaseException(__(':repository has no published releases yet. Link to a tag or branch instead.', ['repository' => $repository]));
            }

            $ref = $published['tag'] ?? $ref;
            $version = AppVersion::normalize($published['tag'] ?? $ref);

            if ($version !== null) {
                $this->assertInstallable($version, allowSame: false);
            }

            $notes = ReleaseNotes::fromBody($published['body'] ?? null, $published['name'] ?? null);
        } catch (ReleaseException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }

        $release = AppRelease::query()->create([
            // Branches and bare tags learn their version from the package.
            'version' => $version ?? (string) $ref,
            'title' => $notes['title'],
            'notes' => $notes['notes'],
            'features' => $notes['features'],
            'source' => AppReleaseSource::GitHub,
            'source_ref' => $repository.'@'.$ref.(filled($published['asset_url'] ?? null) ? '#'.$published['asset_url'] : ''),
            'status' => AppReleaseStatus::Ready,
            'created_by' => $request->user()?->id,
        ]);

        return $this->startInstall($release, $request);
    }

    public function install(ConfirmReleaseActionRequest $request, AppRelease $release): RedirectResponse
    {
        $this->assertIdle();

        abort_unless(in_array($release->status, [AppReleaseStatus::Ready, AppReleaseStatus::Failed], true), 422, __('This release cannot be installed.'));

        if ($release->source === AppReleaseSource::Upload && blank($release->package_path)) {
            throw ValidationException::withMessages(['current_password' => __('The package for this release was removed. Upload it again.')]);
        }

        return $this->startInstall($release, $request);
    }

    public function rollback(ConfirmReleaseActionRequest $request, AppRelease $release): RedirectResponse
    {
        $this->assertIdle();

        if (! $this->installer->status()['enabled']) {
            throw ValidationException::withMessages(['current_password' => (string) $this->installer->status()['reason']]);
        }

        if (! $this->installer->canRollBackTo($release)) {
            throw ValidationException::withMessages(['current_password' => __('Version :version is no longer on the server.', ['version' => $release->version])]);
        }

        $this->start($release, ReleaseRunner::ROLLBACK);

        $this->audit->handle(PlatformAuditAction::ReleaseRollbackStarted, $request->user(), 'app_release', $release->id, [
            'version' => AppVersion::current(),
        ], [
            'version' => $release->version,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rolling back to :version…', ['version' => $release->version])]);

        return to_route('platform.updates.index');
    }

    public function destroy(ConfirmReleaseActionRequest $request, AppRelease $release): RedirectResponse
    {
        abort_unless(in_array($release->status, [AppReleaseStatus::Ready, AppReleaseStatus::Failed], true), 422, __('Only packages that are not installed can be removed.'));

        if (filled($release->package_path)) {
            Storage::disk('local')->delete((string) $release->package_path);
        }

        $this->audit->handle(PlatformAuditAction::ReleaseDeleted, $request->user(), 'app_release', $release->id, [
            'version' => $release->version,
        ]);

        $release->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Package removed.')]);

        return to_route('platform.updates.index');
    }

    public function log(AppRelease $release): JsonResponse
    {
        return response()->json([
            'status' => $release->status->value,
            'log' => $release->log ?? '',
        ]);
    }

    protected function startInstall(AppRelease $release, ConfirmReleaseActionRequest|UploadReleaseRequest|InstallGitHubReleaseRequest $request): RedirectResponse
    {
        if (! $this->installer->status()['enabled']) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('Saved, but this server cannot install updates: :reason', ['reason' => $this->installer->status()['reason']])]);

            return to_route('platform.updates.index');
        }

        $this->start($release, ReleaseRunner::INSTALL);

        $this->audit->handle(PlatformAuditAction::ReleaseInstallStarted, $request->user(), 'app_release', $release->id, [
            'version' => AppVersion::current(),
        ], [
            'version' => $release->version,
            'source' => $release->source->value,
            'source_ref' => $release->source_ref,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installing :version. The site stays online; it switches over when everything is ready.', ['version' => $release->version])]);

        return to_route('platform.updates.index');
    }

    protected function start(AppRelease $release, string $action): void
    {
        try {
            $this->runner->start($release, $action);
        } catch (ReleaseException $exception) {
            $release->forceFill([
                'status' => $action === ReleaseRunner::INSTALL ? AppReleaseStatus::Failed : $release->status,
                'error' => $exception->getMessage(),
            ])->save();

            throw ValidationException::withMessages(['current_password' => $exception->getMessage()]);
        }
    }

    protected function assertIdle(): void
    {
        if ($this->registry->busy()) {
            throw ValidationException::withMessages(['current_password' => __('Another install or rollback is already running.')]);
        }
    }

    protected function assertInstallable(string $version, bool $allowSame): void
    {
        $current = AppVersion::current();
        $comparison = version_compare($version, $current);

        if ($comparison < 0 || ($comparison === 0 && ! $allowSame)) {
            throw new ReleaseException(__('Version :version is not newer than the live version :current. Use rollback to go back to an earlier release.', [
                'version' => $version,
                'current' => $current,
            ]));
        }
    }

    /**
     * Newer GitHub releases than the running version.
     *
     * @return array{error: string|null, checked_at: string, releases: list<array<string, mixed>>}
     */
    protected function available(): array
    {
        $current = AppVersion::current();
        $installed = AppRelease::query()
            ->whereIn('status', [AppReleaseStatus::Active, AppReleaseStatus::Inactive, AppReleaseStatus::Queued, AppReleaseStatus::Installing])
            ->pluck('version')
            ->all();

        try {
            $releases = array_values(collect((array) config('deploy.github.repositories'))
                ->flatMap(fn (string $repository) => collect(Cache::remember(
                    'app-releases:github:'.$repository,
                    300,
                    fn () => $this->github->releases($repository),
                ))->map(fn (array $release) => [...$release, 'repository' => $repository]))
                ->filter(fn (array $release) => $release['version'] !== null && AppVersion::isNewer($release['version'], $current))
                ->sort(fn (array $a, array $b) => version_compare((string) $b['version'], (string) $a['version']))
                ->values()
                ->map(function (array $release) use ($installed): array {
                    $notes = ReleaseNotes::fromBody($release['body'], $release['name']);

                    return [
                        'repository' => $release['repository'],
                        'tag' => $release['tag'],
                        'version' => $release['version'],
                        'name' => $release['name'],
                        'features' => $notes['features'],
                        'notes' => $notes['notes'],
                        'published_at' => $release['published_at'],
                        'prerelease' => $release['prerelease'],
                        'url' => $release['url'] ?? "https://github.com/{$release['repository']}/releases/tag/{$release['tag']}",
                        'has_package' => $release['asset_url'] !== null,
                        'installed' => in_array($release['version'], $installed, true),
                    ];
                })
                ->all());

            return ['error' => null, 'checked_at' => now()->toIso8601String(), 'releases' => $releases];
        } catch (ReleaseException $exception) {
            return ['error' => $exception->getMessage(), 'checked_at' => now()->toIso8601String(), 'releases' => []];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(AppRelease $release): array
    {
        return [
            'id' => $release->id,
            'version' => $release->version,
            'title' => $release->title,
            'notes' => $release->notes,
            'features' => $release->features ?? [],
            'source' => $release->source->value,
            'source_label' => $release->source->label(),
            'source_ref' => $release->source === AppReleaseSource::GitHub
                ? Str::before((string) $release->source_ref, '#')
                : $release->source_ref,
            'status' => $release->status->value,
            'status_label' => $release->status->label(),
            'error' => $release->error,
            'checksum' => $release->checksum,
            'package_size' => $release->package_size,
            'can_install' => in_array($release->status, [AppReleaseStatus::Ready, AppReleaseStatus::Failed], true)
                && ($release->source === AppReleaseSource::GitHub || filled($release->package_path)),
            'can_rollback' => $this->installer->canRollBackTo($release),
            'can_delete' => in_array($release->status, [AppReleaseStatus::Ready, AppReleaseStatus::Failed], true),
            'created_by' => $release->creator?->name,
            'created_at' => $release->created_at?->toIso8601String(),
            'started_at' => $release->started_at?->toIso8601String(),
            'finished_at' => $release->finished_at?->toIso8601String(),
            'activated_at' => $release->activated_at?->toIso8601String(),
        ];
    }
}
