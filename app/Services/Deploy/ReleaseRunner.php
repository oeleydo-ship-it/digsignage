<?php

namespace App\Services\Deploy;

use App\Enums\AppReleaseStatus;
use App\Models\AppRelease;
use Illuminate\Support\Facades\Process;

/**
 * Starts an install or rollback in a detached background process. Installs
 * can take several minutes, outlive the web request that started them, and
 * must survive the queue worker being restarted by the deploy itself.
 */
class ReleaseRunner
{
    public const INSTALL = 'install';

    public const ROLLBACK = 'rollback';

    public function start(AppRelease $release, string $action): void
    {
        if ($action === self::INSTALL) {
            $release->forceFill([
                'status' => AppReleaseStatus::Queued,
                'error' => null,
                'started_at' => now(),
                'finished_at' => null,
            ])->save();
        }

        $log = storage_path('logs/releases.log');

        // "$0".."$3" keep every value out of the shell string itself.
        $result = Process::path(base_path())->run([
            (string) config('deploy.binaries.bash'),
            '-c',
            '(setsid nohup "$0" artisan releases:run "$1" "$2" >> "$3" 2>&1 &)',
            (string) config('deploy.binaries.php'),
            $action,
            (string) $release->id,
            $log,
        ]);

        if ($result->failed()) {
            throw new ReleaseException(__('The installer could not be started: :error', ['error' => trim($result->errorOutput()) ?: 'exit '.$result->exitCode()]));
        }
    }
}
