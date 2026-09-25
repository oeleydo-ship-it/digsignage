<?php

namespace Tests\Feature\Platform;

use App\Enums\AppReleaseSource;
use App\Enums\AppReleaseStatus;
use App\Enums\PlatformAuditAction;
use App\Models\AppRelease;
use App\Models\PlatformAudit;
use App\Models\User;
use App\Services\Deploy\GitHubReleases;
use App\Services\Deploy\ReleaseArchive;
use App\Services\Deploy\ReleaseException;
use App\Services\Deploy\ReleaseInstaller;
use App\Support\AppVersion;
use App\Support\ReleaseNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class AppReleaseTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    /** @var list<string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/digsignage-deploy-'.bin2hex(random_bytes(4));
        $this->temporary[] = $this->base;

        config([
            'deploy.enabled' => false,
            'deploy.base_path' => $this->base,
            'deploy.github.repositories' => ['acme/digsignage'],
            'deploy.github.token' => null,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            is_dir($path) ? File::deleteDirectory($path) : @unlink($path);
        }

        parent::tearDown();
    }

    public function test_regular_users_cannot_open_updates(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.updates.index'))
            ->assertForbidden();
    }

    public function test_the_updates_page_records_the_running_version_and_its_features(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->withoutVite()
            ->get(route('platform.updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/updates/index')
                ->where('current.version', AppVersion::current())
                ->where('current.release.source', 'existing')
                ->where('updater.enabled', false)
                ->has('releases', 1)
                ->missing('available'));

        $release = AppRelease::query()->sole();
        $this->assertSame(AppReleaseStatus::Active, $release->status);
        $this->assertNotEmpty($release->features, 'Features come from CHANGELOG.md');

        // A second visit does not duplicate the record.
        $this->actingAs($admin)->withoutVite()->get(route('platform.updates.index'))->assertOk();
        $this->assertSame(1, AppRelease::query()->count());
    }

    public function test_uploads_require_the_administrators_password(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.upload'), [
                'package' => $this->upload('9.1.0'),
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame(0, AppRelease::query()->count());
    }

    public function test_an_uploaded_package_is_checked_and_listed_with_its_new_features(): void
    {
        Storage::fake('local');
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.updates.upload'), [
                'package' => $this->upload('9.1.0'),
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $release = AppRelease::query()->sole();
        $this->assertSame('9.1.0', $release->version);
        $this->assertSame(AppReleaseStatus::Ready, $release->status);
        $this->assertSame(AppReleaseSource::Upload, $release->source);
        $this->assertSame('Kiosk season', $release->title);
        $this->assertSame(['Visitor check-in kiosk', 'Screen groups by floor'], $release->features);
        $this->assertSame(64, strlen((string) $release->checksum));
        Storage::disk('local')->assertExists((string) $release->package_path);
        $this->assertTrue(PlatformAudit::query()->where('action', PlatformAuditAction::ReleaseUploaded)->exists());
    }

    public function test_packages_that_are_not_newer_are_refused(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.upload'), [
                'package' => $this->upload('0.0.1'),
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('package');

        $this->assertSame(0, AppRelease::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('releases/packages'));
    }

    public function test_a_wrong_checksum_is_refused(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.upload'), [
                'package' => $this->upload('9.1.0'),
                'checksum' => str_repeat('a', 64),
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('package');
    }

    public function test_packages_that_write_outside_the_release_folder_are_refused(): void
    {
        $zip = $this->zip('9.1.0', ['../../evil.php' => '<?php echo 1;']);

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('unsafe path');

        app(ReleaseArchive::class)->inspect($zip);
    }

    public function test_github_source_zips_with_a_top_level_folder_are_accepted(): void
    {
        $zip = $this->zip('9.2.0', prefix: 'acme-digsignage-1a2b3c/');
        $package = app(ReleaseArchive::class)->inspect($zip);

        $this->assertSame('9.2.0', $package->version);
        $this->assertSame('acme-digsignage-1a2b3c/', $package->root);
        $this->assertFalse($package->hasVendor);
    }

    public function test_nothing_starts_while_the_updater_is_off(): void
    {
        Storage::fake('local');
        Process::fake();

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.upload'), [
                'package' => $this->upload('9.1.0'),
                'install_now' => true,
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(AppReleaseStatus::Ready, AppRelease::query()->sole()->status);
        Process::assertNothingRan();
    }

    public function test_install_starts_the_background_installer(): void
    {
        $this->enableUpdater();
        Process::fake();
        $release = AppRelease::factory()->create(['version' => '9.1.0', 'package_path' => 'releases/packages/x.zip']);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.updates.install', $release), ['current_password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertSame(AppReleaseStatus::Queued, $release->refresh()->status);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command)
            && in_array('releases:run', explode(' ', (string) $process->command[2]), true)
            && in_array((string) $release->id, $process->command, true));
        $this->assertTrue(PlatformAudit::query()->where('action', PlatformAuditAction::ReleaseInstallStarted)->exists());
    }

    public function test_a_second_install_is_refused_while_one_is_running(): void
    {
        $this->enableUpdater();
        Process::fake();
        AppRelease::factory()->create(['status' => AppReleaseStatus::Installing, 'started_at' => now()]);
        $next = AppRelease::factory()->create(['version' => '9.3.0', 'package_path' => 'releases/packages/y.zip']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.install', $next), ['current_password' => 'password'])
            ->assertSessionHasErrors('current_password');

        Process::assertNothingRan();
    }

    public function test_the_installer_unpacks_the_release_and_makes_it_live(): void
    {
        $this->enableUpdater();
        Storage::fake('local');
        Process::fake();

        $previous = AppRelease::factory()->active()->create(['version' => '1.0.0']);
        $release = $this->storedRelease('9.1.0');

        app(ReleaseInstaller::class)->install($release);
        $release->refresh();

        $this->assertSame(AppReleaseStatus::Active, $release->status, (string) $release->log);
        $this->assertSame(AppReleaseStatus::Inactive, $previous->refresh()->status);
        $this->assertFileExists($release->release_path.'/artisan');
        $this->assertFileDoesNotExist($release->release_path.'/.env', 'The shared .env is never taken from a package');
        $this->assertNull($release->package_path);
        $this->assertSame(['Visitor check-in kiosk', 'Screen groups by floor'], $release->features);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command)
            && str_ends_with((string) $process->command[1], 'release.sh')
            && $process->command[2] === $release->release_path);
    }

    public function test_a_failed_deploy_script_keeps_the_live_version_and_cleans_up(): void
    {
        $this->enableUpdater();
        Storage::fake('local');
        Process::fake(['*' => Process::result(output: 'migrations failed', exitCode: 1)]);

        $live = AppRelease::factory()->active()->create(['version' => '1.0.0']);
        $release = $this->storedRelease('9.1.0');

        app(ReleaseInstaller::class)->install($release);
        $release->refresh();

        $this->assertSame(AppReleaseStatus::Failed, $release->status);
        $this->assertStringContainsString('exit code 1', (string) $release->error);
        $this->assertStringContainsString('migrations failed', (string) $release->log);
        $this->assertSame(AppReleaseStatus::Active, $live->refresh()->status);
        $this->assertSame(['.', '..'], scandir($this->base.'/releases'));
    }

    public function test_rolling_back_switches_to_an_earlier_release(): void
    {
        $this->enableUpdater();
        Process::fake();

        $folder = $this->base.'/releases/1.0.0-20260101000000';
        File::ensureDirectoryExists($folder);
        $earlier = AppRelease::factory()->create([
            'version' => '1.0.0',
            'status' => AppReleaseStatus::Inactive,
            'release_path' => $folder,
        ]);
        $live = AppRelease::factory()->active()->create(['version' => '9.1.0']);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->withoutVite()
            ->get(route('platform.updates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('releases', fn ($releases) => collect($releases)->firstWhere('id', $earlier->id)['can_rollback'] === true));

        app(ReleaseInstaller::class)->rollback($earlier);

        $this->assertSame(AppReleaseStatus::Active, $earlier->refresh()->status);
        $this->assertSame(AppReleaseStatus::Inactive, $live->refresh()->status);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command)
            && str_ends_with((string) $process->command[1], 'rollback.sh'));
    }

    public function test_github_links_are_parsed_and_limited_to_allowed_repositories(): void
    {
        $github = app(GitHubReleases::class);

        $this->assertSame(['repository' => 'acme/digsignage', 'ref' => 'v1.2.0'], $github->parseUrl('https://github.com/acme/digsignage/releases/tag/v1.2.0'));
        $this->assertSame(['repository' => 'acme/digsignage', 'ref' => 'feature/kiosk'], $github->parseUrl('github.com/Acme/DigSignage/tree/feature/kiosk'));
        $this->assertSame(['repository' => 'acme/digsignage', 'ref' => null], $github->parseUrl('https://github.com/acme/digsignage.git'));

        foreach (['https://gitlab.com/acme/digsignage', 'https://github.com/someone-else/digsignage', 'https://github.com/acme/digsignage/tree/..%2Fsecrets'] as $url) {
            try {
                $github->parseUrl($url);
                $this->fail("{$url} should be refused");
            } catch (ReleaseException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_check_for_updates_lists_newer_github_releases_with_their_features(): void
    {
        Http::fake([
            'api.github.com/repos/acme/digsignage/releases*' => Http::response([
                [
                    'tag_name' => 'v9.2.0',
                    'name' => 'Kiosk season',
                    'body' => "### Added\n- Visitor check-in kiosk\n\n### Fixed\n- Clock drift",
                    'draft' => false,
                    'prerelease' => false,
                    'published_at' => '2026-10-01T10:00:00Z',
                    'html_url' => 'https://github.com/acme/digsignage/releases/tag/v9.2.0',
                    'assets' => [['name' => 'digsignage-9.2.0.zip', 'url' => 'https://api.github.com/repos/acme/digsignage/releases/assets/1', 'size' => 1000]],
                ],
                ['tag_name' => 'v0.1.0', 'draft' => false, 'body' => ''],
                ['tag_name' => 'v9.9.9', 'draft' => true, 'body' => ''],
            ]),
        ]);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->withoutVite()
            ->get(route('platform.updates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('available', fn (Assert $reload) => $reload
                    ->where('available.error', null)
                    ->has('available.releases', 1)
                    ->where('available.releases.0.version', '9.2.0')
                    ->where('available.releases.0.features', ['Visitor check-in kiosk'])
                    ->where('available.releases.0.has_package', true)));
    }

    public function test_installing_from_a_github_link_records_the_source_and_starts(): void
    {
        $this->enableUpdater();
        Process::fake();
        Http::fake([
            'api.github.com/repos/acme/digsignage/releases/tags/v9.2.0' => Http::response([
                'tag_name' => 'v9.2.0',
                'name' => 'Kiosk season',
                'body' => '- Visitor check-in kiosk',
                'assets' => [['name' => 'digsignage-9.2.0.zip', 'url' => 'https://api.github.com/repos/acme/digsignage/releases/assets/7']],
            ]),
        ]);

        $this->actingAs(User::factory()->platformAdmin()->create())
            ->post(route('platform.updates.github'), [
                'url' => 'https://github.com/acme/digsignage/releases/tag/v9.2.0',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $release = AppRelease::query()->where('source', AppReleaseSource::GitHub)->sole();
        $this->assertSame('9.2.0', $release->version);
        $this->assertSame('acme/digsignage@v9.2.0#https://api.github.com/repos/acme/digsignage/releases/assets/7', $release->source_ref);
        $this->assertSame(['Visitor check-in kiosk'], $release->features);
        $this->assertSame(AppReleaseStatus::Queued, $release->status);
    }

    public function test_the_pipeline_can_record_a_deployed_release(): void
    {
        AppRelease::factory()->active()->create(['version' => '1.0.0']);

        $this->artisan('releases:record', ['version' => 'v9.1.0', '--ref' => 'v9.1.0'])
            ->assertSuccessful();

        $release = AppRelease::query()->where('version', '9.1.0')->sole();
        $this->assertSame(AppReleaseSource::Pipeline, $release->source);
        $this->assertSame(AppReleaseStatus::Active, $release->status);
        $this->assertSame(1, AppRelease::query()->where('status', AppReleaseStatus::Active)->count());
    }

    public function test_changelog_sections_become_titles_and_feature_lists(): void
    {
        $changelog = "# Changelog\n\n## [2.0.0] - 2026-11-01 - Big one\n\n### Added\n\n- One\n- Two\n\n### Fixed\n\n- Bug\n\n## [1.0.0] - 2026-09-01\n\n- Old";

        $notes = ReleaseNotes::fromChangelog($changelog, '2.0.0');

        $this->assertSame('Big one', $notes['title']);
        $this->assertSame(['One', 'Two'], $notes['features']);
        $this->assertStringNotContainsString('Old', (string) $notes['notes']);
        $this->assertSame(['Old'], ReleaseNotes::fromChangelog($changelog, '1.0.0')['features']);
        $this->assertSame([], ReleaseNotes::fromChangelog($changelog, '3.0.0')['features']);
    }

    private function enableUpdater(): void
    {
        File::ensureDirectoryExists($this->base.'/releases');
        File::ensureDirectoryExists($this->base.'/shared');
        config(['deploy.enabled' => true]);
    }

    private function storedRelease(string $version): AppRelease
    {
        $path = 'releases/packages/'.$version.'.zip';
        Storage::disk('local')->put($path, (string) file_get_contents($this->zip($version)));

        return AppRelease::factory()->create([
            'version' => $version,
            'features' => null,
            'package_path' => $path,
        ]);
    }

    private function upload(string $version): UploadedFile
    {
        return new UploadedFile($this->zip($version), 'digsignage-'.$version.'.zip', 'application/zip', null, true);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function zip(string $version, array $extra = [], string $prefix = ''): string
    {
        $path = sys_get_temp_dir().'/release-'.bin2hex(random_bytes(4)).'.zip';
        $this->temporary[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        $files = [
            'artisan' => "#!/usr/bin/env php\n<?php\n",
            'composer.json' => '{}',
            'VERSION' => $version."\n",
            'CHANGELOG.md' => "# Changelog\n\n## [{$version}] - 2026-10-01 - Kiosk season\n\n### Added\n\n- Visitor check-in kiosk\n- Screen groups by floor\n\n### Fixed\n\n- Clock drift\n",
            '.env' => 'APP_KEY=from-package',
            'app/Example.php' => '<?php',
            ...$extra,
        ];

        foreach ($files as $name => $contents) {
            $zip->addFromString($prefix.$name, $contents);
        }

        $zip->close();

        return $path;
    }
}
