<?php

namespace App\Actions\Template;

use App\Actions\Design\SaveDesign;
use App\Models\Design;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;

class InstantiateTemplate
{
    /**
     * Create an editable team design from a template canvas.
     */
    public function handle(User $user, Team $team, Template $template): Design
    {
        return app(SaveDesign::class)->handle($user, $team, [
            'name' => $template->name,
            'description' => $template->description,
            'document' => $template->normalizedDocument(),
        ]);
    }
}
