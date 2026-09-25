<?php

namespace App\Enums;

enum AppReleaseStatus: string
{
    /** Package received, install not started. */
    case Ready = 'ready';

    /** Install requested; the installer process is starting. */
    case Queued = 'queued';

    case Installing = 'installing';

    /** The release the `current` symlink points at. */
    case Active = 'active';

    /** Previously active; its folder may still be on disk for rollback. */
    case Inactive = 'inactive';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready to install',
            self::Queued => 'Queued',
            self::Installing => 'Installing',
            self::Active => 'Live',
            self::Inactive => 'Previous',
            self::Failed => 'Failed',
        };
    }

    public function isRunning(): bool
    {
        return in_array($this, [self::Queued, self::Installing], true);
    }
}
