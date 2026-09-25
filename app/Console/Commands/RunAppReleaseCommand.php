<?php

namespace App\Console\Commands;

use App\Enums\AppReleaseStatus;
use App\Models\AppRelease;
use App\Services\Deploy\ReleaseInstaller;
use App\Services\Deploy\ReleaseRunner;
use Illuminate\Console\Command;

class RunAppReleaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'releases:run {action : install or rollback} {release : Release id}';

    /**
     * @var string
     */
    protected $description = 'Install or roll back to an application release (started by the Updates page)';

    public function handle(ReleaseInstaller $installer): int
    {
        $release = AppRelease::query()->find((int) $this->argument('release'));

        if ($release === null) {
            $this->error('Release not found.');

            return self::FAILURE;
        }

        match ((string) $this->argument('action')) {
            ReleaseRunner::INSTALL => $installer->install($release),
            ReleaseRunner::ROLLBACK => $installer->rollback($release),
            default => $this->error('Unknown action; use install or rollback.'),
        };

        $release->refresh();
        $this->line("{$release->version}: {$release->status->label()}".($release->error ? " — {$release->error}" : ''));

        return $release->status === AppReleaseStatus::Active ? self::SUCCESS : self::FAILURE;
    }
}
