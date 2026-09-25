<?php

namespace App\Enums;

enum AppReleaseSource: string
{
    /** Downloaded from a GitHub release, tag or branch. */
    case GitHub = 'github';

    /** Zip package uploaded on the Updates page. */
    case Upload = 'upload';

    /** Deployed by the GitHub Actions workflow and recorded afterwards. */
    case Pipeline = 'pipeline';

    /** The version that was running when release tracking started. */
    case Existing = 'existing';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::Upload => 'Uploaded package',
            self::Pipeline => 'GitHub Actions',
            self::Existing => 'Existing install',
        };
    }
}
