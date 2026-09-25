<?php

namespace App\Console\Commands;

use App\Enums\AppReleaseSource;
use App\Services\Deploy\ReleaseRegistry;
use App\Support\AppVersion;
use Illuminate\Console\Command;

class RecordAppReleaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'releases:record
        {version? : Version now live (defaults to the VERSION file)}
        {--path= : Release folder (defaults to this app)}
        {--ref= : Git tag or commit that was deployed}';

    /**
     * @var string
     */
    protected $description = 'Record a release deployed by GitHub Actions so it appears on the Updates page';

    public function handle(ReleaseRegistry $registry): int
    {
        $version = AppVersion::normalize($this->argument('version') ?? AppVersion::current());

        if ($version === null) {
            $this->error('Give a version such as 1.4.0.');

            return self::FAILURE;
        }

        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? (realpath($path) ?: $path) : base_path();
        $active = $registry->active();

        if ($active?->version === $version && $active->release_path === $path) {
            $this->info("{$version} is already recorded as live.");

            return self::SUCCESS;
        }

        $ref = $this->option('ref');
        $registry->record($version, AppReleaseSource::Pipeline, $path, is_string($ref) && $ref !== '' ? $ref : null);

        $this->info("Recorded {$version} as the live release.");

        return self::SUCCESS;
    }
}
