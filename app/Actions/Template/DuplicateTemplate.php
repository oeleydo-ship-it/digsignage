<?php

namespace App\Actions\Template;

use App\Enums\TemplateStatus;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;

class DuplicateTemplate
{
    /**
     * Copy a template into the current team as a draft, or as another catalog item for platform admins.
     */
    public function handle(User $user, Team $team, Template $template, bool $asPlatform = false): Template
    {
        return app(SaveTemplate::class)->handle($user, $team, [
            'name' => $template->name.' copy',
            'description' => $template->description,
            'category' => $template->category->value,
            'status' => TemplateStatus::Draft->value,
            'document' => $template->document,
            'platform' => $asPlatform && $user->is_platform_admin === true,
        ]);
    }
}
